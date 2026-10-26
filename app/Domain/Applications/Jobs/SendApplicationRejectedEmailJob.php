<?php

namespace App\Domain\Applications\Jobs;

use App\Domain\Applications\Enums\IndividualApplicationStatus;
use App\Domain\Applications\Enums\LegalEntityApplicationStatus;
use App\Domain\Applications\Models\IndividualApplication;
use App\Domain\Applications\Models\LegalEntityApplication;
use App\Domain\Mail\EmailType;
use App\Domain\Mail\TemplatedMailer;
use App\Domain\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Tells an applicant their application was rejected, with the reason (IMPLEMENTATION_PLAN §11.4). A8 — both
 * kinds (legal entity and individual), in the applicant's own language (az / ru / en).
 */
class SendApplicationRejectedEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const LEGAL = 'legal';

    public const INDIVIDUAL = 'individual';

    public int $tries = 3;

    public function __construct(public readonly int $applicationId, public readonly string $kind = self::LEGAL)
    {
        $this->onQueue('notifications');
    }

    public function handle(TemplatedMailer $mailer): void
    {
        $application = $this->kind === self::INDIVIDUAL
            ? IndividualApplication::query()->with('user')->find($this->applicationId)
            : LegalEntityApplication::query()->with('applicant')->find($this->applicationId);
        if ($application === null || ! self::isRejected($application)) {
            return;
        }
        $user = $application instanceof IndividualApplication ? $application->user : $application->applicant;
        $to = $user?->email ?: ($application instanceof IndividualApplication ? $application->email : $application->contact_email);
        if (! $mailer->isConfigured()) {
            Log::warning('application.rejection_email_not_sent', ['application_id' => $application->id, 'kind' => $this->kind, 'reason' => 'mailer_not_configured']);

            return;
        }

        $locale = self::locale($user);
        $mailer->send(EmailType::ApplicationRejected, (string) $to, self::emailData($application, $locale), $locale);
    }

    private static function isRejected(IndividualApplication|LegalEntityApplication $application): bool
    {
        return $application instanceof IndividualApplication
            ? $application->status === IndividualApplicationStatus::Rejected
            : $application->status === LegalEntityApplicationStatus::Rejected;
    }

    private static function locale(?User $user): string
    {
        $value = $user?->preferred_language?->value;

        return in_array($value, ['az', 'ru', 'en'], true) ? $value : 'az';
    }

    /** @return array<string, mixed> */
    public static function emailData(IndividualApplication|LegalEntityApplication $application, string $locale = 'az'): array
    {
        $individual = $application instanceof IndividualApplication;
        $name = (string) ($individual ? $application->full_name : $application->contact_person_name);
        $reason = (string) $application->rejection_reason;
        $body = $individual
            ? __('emails.application_rejected.individual_body', ['address' => (string) $application->address], $locale)
            : __('emails.application_rejected.legal_body', ['complex' => (string) $application->complex_name], $locale);

        return [
            'name' => $name,
            'complexName' => $individual ? null : (string) $application->complex_name,
            'reason' => $reason,
            'lines' => [
                __('emails.application_rejected.greeting', ['name' => $name], $locale),
                $body,
                __('emails.application_rejected.reason', ['reason' => $reason], $locale),
                __('emails.application_rejected.retry', [], $locale),
            ],
        ];
    }
}
