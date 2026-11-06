<?php

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Admin\Settings\RuntimeMailer;
use App\Domain\Payments\Events\OrderPaid;
use App\Domain\Payments\Events\OrderPartiallyRefunded;
use App\Domain\Payments\Events\OrderRefunded;
use App\Domain\Payments\Jobs\SendReceiptEmailJob;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Models\Refund;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use Illuminate\Support\Facades\Bus;

/*
| The payer gets a receipt by e-mail after a payment and after a refund, in their language.
*/

function receiptMailer(): RuntimeMailer
{
    $mailer = new class(app(SettingsService::class)) extends RuntimeMailer
    {
        /** @var array<int, array{to: string, subject: string, body: string, html: ?string}> */
        public array $sent = [];

        public function isConfigured(): bool
        {
            return true;
        }

        public function send(string $to, string $subject, string $body, ?string $html = null): void
        {
            $this->sent[] = compact('to', 'subject', 'body', 'html');
        }
    };
    app()->instance(RuntimeMailer::class, $mailer);

    return $mailer;
}

function receiptOrder(string $language = 'az', string $bankOrderId = '294277'): Order
{
    $user = makeUser('+99450'.random_int(1000000, 9999999));
    $user->forceFill(['email' => 'payer@example.test', 'preferred_language' => $language])->save();
    $device = makeActiveDevice('SER-'.uniqid(), '+99470'.random_int(1000000, 9999999));
    $device->update(['location_label' => 'Həyət qapısı']);
    $subscription = makeSubscription(makeDeviceUser($user, $device), ['tier' => SubscriptionTier::Main, 'ends_at' => '2027-11-06 10:00:00']);

    $order = makePaidOrder($user, 1500, $bankOrderId);
    $order->items()->create([
        'item_type' => 'sub_main', 'referenced_id' => $subscription->id, 'description' => 'Əsas', 'quantity' => 1,
        'unit_amount_minor' => 1500, 'total_amount_minor' => 1500,
    ]);
    $order->payments()->update(['card_brand' => 'Visa', 'card_last4' => '1778', 'bank_transaction_id' => '721894577958']);

    return $order->refresh();
}

function receiptRefund(Order $order, int $amountMinor = 500): Refund
{
    return $order->refunds()->create([
        'amount_minor' => $amountMinor, 'reason' => 'test', 'status' => 'approved',
        'requested_by_admin_id' => makeSuperAdmin('r'.uniqid().'@salam.az')->id,
        'bank_refund_id' => '352774575998', 'completed_at' => '2026-11-06 12:00:00',
    ]);
}

beforeEach(function () {
    app(SettingsService::class)->setGroup('payments', ['kapital_mode' => 'production']);
});

it('sends the payer a payment receipt with the seller, service, card and RRN', function () {
    $mailer = receiptMailer();
    $order = receiptOrder();

    (new SendReceiptEmailJob((int) $order->id))->handle(app(\App\Domain\Mail\TemplatedMailer::class));

    expect($mailer->sent)->toHaveCount(1);
    $mail = $mailer->sent[0];
    expect($mail['to'])->toBe('payer@example.test')
        ->and($mail['subject'])->toBe('Salam Sesame — ödəniş qəbzi')
        ->and($mail['body'])->toContain('Alkin Group MMC')
        ->toContain($order->reference)
        ->toContain('15.00 AZN')
        ->toContain('Həyət qapısı, 06.11.2027 tarixinədək')
        ->toContain('Visa •••• 1778')
        ->toContain('721894577958')
        ->not->toContain('Test ödənişidir')
        ->and($mail['html'])->toContain('Alkin Group MMC')->toContain('Salam Sesame');
});

it('sends a refund receipt with the refunded amount', function () {
    $mailer = receiptMailer();
    $order = receiptOrder();
    $refund = receiptRefund($order);

    (new SendReceiptEmailJob((int) $order->id, (int) $refund->id))->handle(app(\App\Domain\Mail\TemplatedMailer::class));

    expect($mailer->sent[0]['subject'])->toBe('Salam Sesame — geri qaytarma qəbzi')
        ->and($mailer->sent[0]['body'])->toContain('5.00 AZN')->toContain('352774575998');
});

it('writes the receipt in the payer\'s language', function () {
    $mailer = receiptMailer();
    $order = receiptOrder('ru');

    (new SendReceiptEmailJob((int) $order->id))->handle(app(\App\Domain\Mail\TemplatedMailer::class));

    expect($mailer->sent[0]['subject'])->toBe('Salam Sesame — квитанция об оплате')
        ->and($mailer->sent[0]['body'])->toContain('Оплата получена')->toContain('до 06.11.2027');
});

it('says so on the receipt while the bank test environment is in use', function () {
    app(SettingsService::class)->setGroup('payments', ['kapital_mode' => 'sandbox']);
    $mailer = receiptMailer();
    $order = receiptOrder();

    (new SendReceiptEmailJob((int) $order->id))->handle(app(\App\Domain\Mail\TemplatedMailer::class));

    expect($mailer->sent[0]['body'])->toContain('Test ödənişidir');
});

it('skips a payer without an e-mail address', function () {
    $mailer = receiptMailer();
    $order = receiptOrder();
    $order->payer->forceFill(['email' => null])->save();

    (new SendReceiptEmailJob((int) $order->id))->handle(app(\App\Domain\Mail\TemplatedMailer::class));

    expect($mailer->sent)->toBeEmpty();
});

it('queues a receipt for a payment and for full and partial refunds', function () {
    Bus::fake([SendReceiptEmailJob::class]);
    $order = receiptOrder();
    $refund = receiptRefund($order);

    OrderPaid::dispatch($order);
    OrderRefunded::dispatch($order, $refund);
    OrderPartiallyRefunded::dispatch($order, $refund);

    Bus::assertDispatched(SendReceiptEmailJob::class, fn ($job) => $job->orderId === $order->id && $job->refundId === null);
    Bus::assertDispatched(SendReceiptEmailJob::class, fn ($job) => $job->refundId === $refund->id);
    Bus::assertDispatchedTimes(SendReceiptEmailJob::class, 3);
});
