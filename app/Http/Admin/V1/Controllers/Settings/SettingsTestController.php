<?php

namespace App\Http\Admin\V1\Controllers\Settings;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Services\SettingsService;
use App\Domain\Admin\Settings\RuntimeMailer;
use App\Domain\Auth\Models\RefreshToken;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\DeviceComm\Contracts\DeviceSmsGateway;
use App\Domain\Payments\Adapters\KapitalEcom\KapitalEcomClient;
use App\Domain\Payments\Adapters\KapitalEcom\KapitalEcomGateway;
use App\Domain\Payments\Exceptions\KapitalEcomApiException;
use App\Domain\Payments\Services\PaymentSettingsValidator;
use App\Domain\Payments\Support\PaymentSettings;
use App\Http\Concerns\AuthorizesAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Settings operational actions (system.settings.manage): connection tests, real test email/OTP sends, live
 * Traccar status, and "force logout all sessions". Every check is real — no mock success, no fake data.
 */
class SettingsTestController
{
    use AuthorizesAdmin;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly RuntimeMailer $mailer,
        private readonly AuditLogger $audit,
        private readonly PaymentSettingsValidator $paymentValidator,
        private readonly PaymentSettings $paymentSettings,
        private readonly KapitalEcomClient $kapital,
        private readonly DeviceSmsGateway $sms,
    ) {}

    /** POST /admin/v1/settings/{group}/test — connection test (traccar/email/payments). */
    public function test(Request $request, string $group): JsonResponse
    {
        $this->requirePermission($request, Permission::SYSTEM_SETTINGS_MANAGE);

        return response()->json(match ($group) {
            'traccar' => $this->testTraccar(),
            'email' => $this->testSmtp(),
            'payments' => $this->testPayments(),
            default => ['ok' => false, 'message' => 'Bu bölmə üçün test mövcud deyil.'],
        });
    }

    /** POST /admin/v1/settings/email/send-test — real test email via runtime SMTP. */
    public function sendTestEmail(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::SYSTEM_SETTINGS_MANAGE);
        $to = (string) $request->validate(['to' => ['required', 'email']])['to'];

        if (! $this->mailer->isConfigured()) {
            return response()->json(['ok' => false, 'message' => 'SMTP host və From Email təyin edilməlidir.']);
        }
        try {
            $this->mailer->send($to, 'Salam Sesame — test e-poçtu', "Bu, Settings bölməsindən göndərilən test mesajıdır.\nƏgər bunu alırsınızsa, SMTP konfiqurasiyası işləyir.");
            $this->audit->record('settings.email_test_sent', ['to' => $to]);

            return response()->json(['ok' => true, 'message' => "Test e-poçtu göndərildi: {$to}"]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Göndərmə uğursuz: '.substr($e->getMessage(), 0, 160)]);
        }
    }

    /** POST /admin/v1/settings/email/send-test-otp — real OTP email using the configured template. */
    public function sendTestOtp(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::SYSTEM_SETTINGS_MANAGE);
        $data = $request->validate([
            'to' => ['required', 'email'],
            'template' => ['nullable', 'in:registration,login'],
        ]);
        $to = (string) $data['to'];
        $template = $data['template'] ?? 'login';

        if (! $this->mailer->isConfigured()) {
            return response()->json(['ok' => false, 'message' => 'SMTP host və From Email təyin edilməlidir.']);
        }

        $length = max(4, (int) $this->settings->value('otp', 'otp_length'));
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $body = str_replace('{code}', $code, (string) $this->settings->value('otp', "tpl_{$template}"));

        try {
            $this->mailer->send($to, 'Salam Sesame — test OTP', $body);
            $this->audit->record('settings.otp_test_sent', ['to' => $to, 'template' => $template]);

            return response()->json(['ok' => true, 'message' => "Test OTP göndərildi: {$to} (şablon: {$template})"]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Göndərmə uğursuz: '.substr($e->getMessage(), 0, 160)]);
        }
    }

    /** POST /admin/v1/settings/sms/send-test — a real SMS through the provider in .env, when one is connected. */
    public function sendTestSms(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::SYSTEM_SETTINGS_MANAGE);
        $to = (string) $request->validate(['to' => ['required', 'string']])['to'];

        if (config('integrations.sms.provider') === 'fake') {
            return response()->json(['ok' => false, 'message' => 'SMS provayderi qoşulmayıb: serverin .env faylında SMS_PROVIDER=fake.']);
        }
        try {
            $sent = $this->sms->sendCommand($to, 'Salam Sesame: test SMS');
            $this->audit->record('settings.sms_test_sent', ['to' => $to, 'sent' => $sent]);

            return response()->json(['ok' => $sent, 'message' => $sent ? "Test SMS göndərildi: {$to}" : 'Provayder SMS-i qəbul etmədi.']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Göndərmə uğursuz: '.substr($e->getMessage(), 0, 160)]);
        }
    }

    /** GET /admin/v1/settings/traccar/status — live Traccar operational status (real reads, no fake data). */
    public function traccarStatus(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::SYSTEM_SETTINGS_MANAGE);

        $ping = $this->testTraccar();

        return response()->json([
            'connection' => $ping['ok'] ? 'connected' : 'offline',
            'version' => $ping['meta']['version'] ?? null,
            'device_count' => $this->safe(fn (): int => (int) DB::table('traccar_devices')->count(), 0),
            'queue_size' => $this->safe(fn (): int => (int) DB::table('whitelist_changes')->where('status', 'pending')->count(), 0),
            'last_webhook' => $this->safe(fn (): ?string => DB::table('device_diagnostics')->max('reported_at'), null),
            'last_command' => $this->safe(fn (): ?string => DB::table('open_commands')->max('created_at'), null),
            'last_ack' => $this->safe(fn (): ?string => DB::table('open_commands')->where('state', 'opened')->max('created_at'), null),
            'last_device_sync' => $this->safe(fn (): ?string => DB::table('whitelist_changes')->where('status', 'synced')->max('updated_at'), null),
        ]);
    }

    /** POST /admin/v1/settings/security/force-logout — global force logout of all sessions. */
    public function forceLogout(Request $request): JsonResponse
    {
        $actor = $this->requirePermission($request, Permission::SYSTEM_SETTINGS_MANAGE);

        // 1) admin tokens: bump the cutoff so any token issued before now is rejected by the guard.
        $now = now()->timestamp;
        $this->settings->set('security.force_logout_at', (string) $now, (int) $actor->getKey());

        // 2) user sessions: revoke every active refresh token.
        $revoked = RefreshToken::query()->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revocation_reason' => 'admin']);

        $this->audit->record('settings.force_logout', ['cutoff' => $now, 'user_tokens_revoked' => $revoked]);

        return response()->json(['ok' => true, 'cutoff' => $now, 'user_tokens_revoked' => $revoked]);
    }

    /**
     * @return array{ok: bool, message: string, meta?: array<string, mixed>}
     */
    private function testTraccar(): array
    {
        $base = (string) config('integrations.traccar.base_url');
        $token = (string) config('integrations.traccar.api_token');
        if ($base === '') {
            return ['ok' => false, 'message' => 'TRACCAR_BASE_URL serverin .env faylında təyin edilməyib.'];
        }
        try {
            $req = Http::timeout(8);
            if ($token !== '') {
                $req = $req->withToken($token);
            }
            $res = $req->get(rtrim($base, '/').'/api/server');
            if ($res->successful()) {
                return ['ok' => true, 'message' => 'Traccar əlçatandır.', 'meta' => ['version' => $res->json('version')]];
            }

            return ['ok' => false, 'message' => 'Traccar HTTP '.$res->status()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Bağlantı xətası: '.substr($e->getMessage(), 0, 120)];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function testSmtp(): array
    {
        $host = (string) $this->settings->value('email', 'smtp_host');
        $port = (int) $this->settings->value('email', 'smtp_port');
        if ($host === '') {
            return ['ok' => false, 'message' => 'SMTP host təyin edilməyib.'];
        }
        $conn = @fsockopen($host, $port ?: 587, $errno, $errstr, 6);
        if ($conn) {
            fclose($conn);

            return ['ok' => true, 'message' => "SMTP {$host}:{$port} əlçatandır."];
        }

        return ['ok' => false, 'message' => "SMTP {$host}:{$port} əlçatan deyil: {$errstr}"];
    }

    /**
     * Real payments Test Connection: checks the login at the bank. Never creates a payment, never charges a card.
     * Returns full diagnostics for the admin status panel.
     *
     * @return array<string, mixed>
     */
    private function testPayments(): array
    {
        $d = $this->paymentValidator->diagnose($this->settings->group('payments'));

        return array_merge($d, ['message' => $d['ok'] ? 'Bank bağlantısı OK.' : 'Giriş uğursuz: '.$d['login']['message']]);
    }

    /** POST /admin/v1/settings/payments/test-create — one unpaid order at the bank: no card is entered, it expires by itself. */
    public function testCreatePayment(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::SYSTEM_SETTINGS_MANAGE);

        try {
            $order = $this->kapital->createOrder(
                100,
                $this->paymentSettings->currency(),
                'Salam Sesame',
                'Test connection (no charge)',
                'az',
                $this->paymentSettings->returnUrl(),
            );
            $this->audit->record('settings.payment_test_create', ['payment_id' => (string) $order['id'], 'status' => $order['status'] ?? null]);

            return response()->json([
                'ok' => true,
                'payment_id' => (string) $order['id'],
                'status' => $order['status'] ?? null,
                'confirm_url' => KapitalEcomGateway::hostedPageUrl($order),
                'has_confirm_url' => true,
            ]);
        } catch (KapitalEcomApiException $e) {
            return response()->json(['ok' => false, 'code' => $e->bankCode(), 'message' => $e->getMessage()]);
        }
    }

    private function safe(callable $fn, mixed $default): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return $default;
        }
    }
}
