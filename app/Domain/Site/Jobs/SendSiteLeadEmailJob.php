<?php

namespace App\Domain\Site\Jobs;

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Mail\EmailType;
use App\Domain\Mail\TemplatedMailer;
use App\Domain\Site\Models\SiteLead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Tells the team about a new website request. The address comes from Settings (Ümumi › Sayt müraciətləri üçün
 * e-poçt). The request itself is safe in the admin panel, so an unset address or a failed send loses nothing.
 */
class SendSiteLeadEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $leadId)
    {
        $this->onQueue('notifications');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(TemplatedMailer $mailer, SettingsService $settings): void
    {
        $lead = SiteLead::query()->find($this->leadId);
        $to = trim((string) $settings->value('general', 'site_lead_email'));
        if ($lead === null || $to === '') {
            return;
        }

        if (! $mailer->isConfigured()) {
            Log::warning('site.lead_email_not_sent', ['lead_id' => $lead->id, 'reason' => 'mailer_not_configured']);

            return;
        }

        $mailer->send(EmailType::SiteLead, $to, self::emailData($lead));
    }

    /** @return array<string, mixed> */
    public static function emailData(SiteLead $lead): array
    {
        $rows = array_values(array_filter([
            ['Ad', (string) $lead->name],
            ['E-poçt', (string) $lead->email],
            ['Telefon', (string) $lead->phone],
            ['Məkan', (string) $lead->place],
            $lead->topic ? ['Mövzu', (string) $lead->topic] : null,
            $lead->message ? ['Mesaj', (string) $lead->message] : null,
        ]));

        return [
            'brand' => 'Salam Sesame',
            'title' => 'Saytdan yeni müraciət',
            'rows' => $rows,
            'notice' => null,
            'lines' => ['Saytdan yeni müraciət', ...array_map(static fn (array $row): string => $row[0].': '.$row[1], $rows)],
        ];
    }
}
