<?php

namespace App\Http\Api\V1\Controllers\Payments;

use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Services\PaymentVerifierService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GET /v1/payments/return — paymentReturn. Where BirPay redirects the customer's browser after the hosted page.
 * Query params are NOT trusted: the paymentId is used only to locate our order; the authoritative status comes
 * from getOrderStatus (R-PAY-04). Renders a result page and deep-links back into the app.
 */
class PaymentReturnController
{
    public function __construct(private readonly PaymentVerifierService $verifier) {}

    public function __invoke(Request $request): Response
    {
        $paymentId = (string) ($request->query('paymentId') ?? $request->query('id') ?? '');
        $order = $paymentId !== '' ? Order::query()->where('bank_order_id', $paymentId)->first() : null;

        if ($order !== null) {
            try {
                $this->verifier->verifyAndApply($order); // authoritative GET; never trusts the redirect
                $order->refresh();
            } catch (\Throwable) {
                // network/transient — the reconciler is the safety net; show the current known status
            }
        }

        $status = $order?->status ?? null;
        $outcome = match (true) {
            $status === OrderStatus::Paid => 'success',
            $status === OrderStatus::Failed, $status === OrderStatus::Expired => 'failure',
            $status === OrderStatus::Cancelled => 'cancel',
            default => 'pending',
        };

        $deepLink = 'salam://payment/return?status='.$outcome.'&order='.rawurlencode((string) ($order?->reference ?? ''));

        return new Response($this->page($outcome, $order?->reference, $deepLink), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function page(string $outcome, ?string $reference, string $deepLink): string
    {
        [$title, $color, $icon] = match ($outcome) {
            'success' => ['Ödəniş uğurlu', '#16a34a', '✓'],
            'failure' => ['Ödəniş uğursuz', '#dc2626', '✕'],
            'cancel' => ['Ödəniş ləğv edildi', '#d97706', '!'],
            default => ['Ödəniş emal olunur', '#2563eb', '…'],
        };
        $ref = $reference !== null ? htmlspecialchars($reference, ENT_QUOTES) : '';
        $link = htmlspecialchars($deepLink, ENT_QUOTES);

        return <<<HTML
<!doctype html><html lang="az"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title>
<style>body{font-family:system-ui,sans-serif;background:#f8fafc;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center}
.card{background:#fff;border-radius:16px;padding:32px;max-width:360px;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,.08)}
.icon{width:64px;height:64px;border-radius:50%;background:{$color};color:#fff;font-size:32px;line-height:64px;margin:0 auto 16px}
h1{font-size:20px;margin:0 0 8px;color:#0f172a}p{color:#64748b;margin:4px 0}a.btn{display:inline-block;margin-top:20px;background:{$color};color:#fff;text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:600}</style>
</head><body><div class="card"><div class="icon">{$icon}</div>
<h1>{$title}</h1><p>Sifariş: {$ref}</p><p>Tətbiqə qayıdırsınız…</p>
<a class="btn" href="{$link}">Tətbiqə qayıt</a></div>
<script>setTimeout(function(){location.href="{$link}"},800)</script>
</body></html>
HTML;
    }
}
