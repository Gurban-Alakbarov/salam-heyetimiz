<?php

namespace App\Domain\Applications\Jobs;

use App\Domain\Applications\Enums\LegalEntityApplicationStatus;
use App\Domain\Applications\Models\LegalEntityApplication;
use App\Domain\Mail\EmailType;
use App\Domain\Mail\TemplatedMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/** Tells a legal-entity applicant their application was rejected, with the reason (IMPLEMENTATION_PLAN §11.4). */
class SendApplicationRejectedEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $applicationId)
    {
        $this->onQueue('notifications');
    }

    public function handle(TemplatedMailer $mailer): void
    {
        $application = LegalEntityApplication::query()->with('applicant')->find($this->applicationId);
        if ($application === null || $application->status !== LegalEntityApplicationStatus::Rejected) {
            return;
        }
        $to = $application->applicant?->email ?: $application->contact_email;
        if (! $mailer->isConfigured()) {
            Log::warning('application.rejection_email_not_sent', ['application_id' => $application->id, 'reason' => 'mailer_not_configured']);

            return;
        }

        $mailer->send(EmailType::ApplicationRejected, (string) $to, self::emailData($application), 'az');
    }

    /** @return array<string, mixed> */
    public static function emailData(LegalEntityApplication $application): array
    {
        $name = (string) $application->contact_person_name;

        return [
            'name' => $name,
            'complexName' => (string) $application->complex_name,
            'reason' => (string) $application->rejection_reason,
            'lines' => [
                'Salam, '.$name.'!',
                '"'.$application->complex_name.'" üçün göndərdiyiniz müraciət təəssüf ki, təsdiqlənmədi.',
                'Səbəb: '.$application->rejection_reason,
                'Məlumatları düzəldib yenidən müraciət edə bilərsiniz.',
            ],
        ];
    }
}
