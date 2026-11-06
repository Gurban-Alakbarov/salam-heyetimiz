<?php

namespace App\Domain\Payments\Jobs;

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Mail\EmailType;
use App\Domain\Mail\TemplatedMailer;
use App\Domain\Payments\Adapters\FakeKapitalGateway;
use App\Domain\Payments\Enums\PaymentType;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Models\Refund;
use App\Domain\Payments\Support\PaymentSettings;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * E-mails the payer a receipt after a payment or a refund, in their language. It is a receipt only, not a tax
 * invoice. It never touches the payment: a payer without an address, or a failed send, leaves the order as it is.
 */
class SendReceiptEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    private const SELLER = 'Alkin Group MMC';

    private const BRAND = 'Salam Sesame';

    private const TIMEZONE = 'Asia/Baku';

    public int $tries = 3;

    public function __construct(public readonly int $orderId, public readonly ?int $refundId = null)
    {
        $this->onQueue('notifications');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(TemplatedMailer $mailer): void
    {
        $order = Order::query()->with(['payer', 'items', 'payments'])->find($this->orderId);
        $refund = $this->refundId !== null ? Refund::query()->find($this->refundId) : null;
        $to = $order?->payer?->email;
        if ($order === null || ($this->refundId !== null && $refund === null) || empty($to)) {
            return;
        }

        if (! $mailer->isConfigured()) {
            Log::warning('payments.receipt_not_sent', ['order_id' => $order->id, 'reason' => 'mailer_not_configured']);

            return;
        }

        $locale = self::locale($order->payer);
        $type = $refund !== null ? EmailType::RefundReceipt : EmailType::PaymentReceipt;
        $mailer->send($type, (string) $to, self::emailData($order, $refund, $locale), $locale);
    }

    private static function locale(?User $user): string
    {
        $value = $user?->preferred_language?->value;

        return in_array($value, ['az', 'ru', 'en'], true) ? $value : 'az';
    }

    /** @return array<string, mixed> */
    public static function emailData(Order $order, ?Refund $refund, string $locale = 'az'): array
    {
        $title = __($refund !== null ? 'emails.receipt.refund_title' : 'emails.receipt.paid_title', [], $locale);
        $rows = $refund !== null ? self::refundRows($order, $refund, $locale) : self::paymentRows($order, $locale);
        $notice = self::isTest($order) ? __('emails.receipt.test_notice', [], $locale) : null;

        return [
            'brand' => self::BRAND,
            'supportEmail' => (string) app(SettingsService::class)->value('general', 'support_email'),
            'title' => $title,
            'rows' => $rows,
            'notice' => $notice,
            'lines' => array_values(array_filter([
                $title,
                ...array_map(static fn (array $row): string => $row[0].': '.$row[1], $rows),
                $notice,
            ])),
        ];
    }

    /** @return array<int, array{0: string, 1: string}> */
    private static function paymentRows(Order $order, string $locale): array
    {
        $charge = $order->payments->where('type', PaymentType::Charge)->sortByDesc('id')->first();
        $card = trim((string) $charge?->card_brand.' •••• '.(string) $charge?->card_last4);

        return array_values(array_filter([
            [__('emails.receipt.seller', [], $locale), self::SELLER],
            [__('emails.receipt.order', [], $locale), (string) $order->reference],
            [__('emails.receipt.date', [], $locale), self::date($order->paid_at)],
            ...self::services($order, $locale),
            [__('emails.receipt.amount', [], $locale), self::money((int) $order->amount_minor, (string) $order->currency)],
            $charge?->card_last4 ? [__('emails.receipt.card', [], $locale), $card] : null,
            $charge?->bank_transaction_id ? ['RRN', (string) $charge->bank_transaction_id] : null,
        ]));
    }

    /** @return array<int, array{0: string, 1: string}> */
    private static function refundRows(Order $order, Refund $refund, string $locale): array
    {
        return array_values(array_filter([
            [__('emails.receipt.seller', [], $locale), self::SELLER],
            [__('emails.receipt.order', [], $locale), (string) $order->reference],
            [__('emails.receipt.date', [], $locale), self::date($refund->completed_at)],
            [__('emails.receipt.refund_amount', [], $locale), self::money((int) $refund->amount_minor, (string) $order->currency)],
            $refund->bank_refund_id ? ['RRN', (string) $refund->bank_refund_id] : null,
        ]));
    }

    /**
     * What was paid for: each gate and the date its subscription now runs to.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private static function services(Order $order, string $locale): array
    {
        $rows = [];
        foreach ($order->items as $item) {
            $subscription = $item->referenced_id !== null
                ? Subscription::query()->with('deviceUser.device')->find($item->referenced_id)
                : null;
            $device = $subscription?->deviceUser?->device;
            if ($device === null) {
                continue;
            }
            $rows[] = [
                __('emails.receipt.service', [], $locale),
                __('emails.receipt.service_value', [
                    'door' => $device->location_label ?: $device->serial,
                    'date' => $subscription->ends_at->copy()->timezone(self::TIMEZONE)->format('d.m.Y'),
                ], $locale),
            ];
        }

        return $rows;
    }

    /** While the bank's test environment is in use no real money moves, and the receipt says so. */
    private static function isTest(Order $order): bool
    {
        return FakeKapitalGateway::isFakeBankOrderId($order->bank_order_id)
            || app(PaymentSettings::class)->mode() !== 'production';
    }

    private static function date(?CarbonInterface $at): string
    {
        return $at?->copy()->timezone(self::TIMEZONE)->format('d.m.Y H:i') ?? '';
    }

    private static function money(int $minor, string $currency): string
    {
        return number_format($minor / 100, 2, '.', '').' '.$currency;
    }
}
