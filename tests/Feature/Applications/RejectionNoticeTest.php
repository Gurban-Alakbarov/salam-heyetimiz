<?php

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Admin\Settings\RuntimeMailer;
use App\Domain\Applications\Jobs\SendApplicationRejectedEmailJob;
use App\Domain\Applications\Models\IndividualApplication;
use App\Domain\Applications\Models\LegalEntityApplication;
use App\Domain\Notifications\Models\Notification;
use Database\Seeders\Notifications\NotificationTemplatesSeeder;
use Illuminate\Support\Facades\Bus;

/*
| A8 — a rejected applicant is always told: individual applications now too (email + in the app), and the
| email comes in the applicant's language (az / ru / en), subject included.
*/

beforeEach(function () {
    (new NotificationTemplatesSeeder)->run();
    $this->super = makeSuperAdmin('a8-super@salamhayetimiz.az');
});

function a8Mailer(): RuntimeMailer
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

function a8User(string $phone, string $lang = 'az')
{
    $u = makeUser($phone);
    $u->forceFill(['email' => 'a8'.substr($phone, -4).'@example.test', 'email_verified_at' => now(), 'preferred_language' => $lang])->save();

    return $u->refresh();
}

it('tells a rejected individual applicant by email and in the app', function () {
    Bus::fake([SendApplicationRejectedEmailJob::class]);
    $user = a8User('+994500080801');
    $id = $this->actingAs($user, 'user')->postJson('/v1/applications/individual', [
        'full_name' => 'Rauf Həsənov', 'phone' => '+994501110001', 'email' => 'rauf@example.test',
        'address' => 'Bakı, Nəsimi r., Həyət 12', 'latitude' => 40.4093, 'longitude' => 49.8671,
    ])->assertCreated()->json('data.id');

    $this->actingAs($this->super, 'admin')->patchJson("/admin/v1/applications/individual/{$id}", [
        'status' => 'rejected', 'rejection_reason' => 'Ünvan xidmət zonasından kənardır',
    ])->assertOk();

    Bus::assertDispatched(SendApplicationRejectedEmailJob::class, fn ($j) => $j->applicationId === $id && $j->kind === 'individual');
    $inapp = Notification::query()->where('user_id', $user->id)->where('template_key', 'application.rejected')->where('channel', 'inapp')->sole();
    expect($inapp->payload['type'])->toBe('application_rejected')
        ->and($inapp->payload['body'])->toContain('Ünvan xidmət zonasından kənardır');
});

it('writes the individual rejection email in Azerbaijani', function () {
    $mailer = a8Mailer();
    $user = a8User('+994500080802');
    $app = IndividualApplication::query()->create([
        'user_id' => $user->id, 'full_name' => 'Rauf Həsənov', 'phone' => '+994501110001', 'email' => 'rauf@example.test',
        'address' => 'Bakı, Həyət 12', 'latitude' => 40.4, 'longitude' => 49.8, 'status' => 'rejected', 'rejection_reason' => 'Zona xaricində',
    ]);

    (new SendApplicationRejectedEmailJob((int) $app->id, 'individual'))->handle(app(\App\Domain\Mail\TemplatedMailer::class));

    $mail = $mailer->sent[0];
    expect($mail['to'])->toBe($user->email)
        ->and($mail['body'])->toContain('Salam, Rauf Həsənov!')->toContain('Bakı, Həyət 12')->toContain('Səbəb: Zona xaricində')
        ->and($mail['html'])->toContain('lang="az"');
});

it('writes the legal rejection email — subject included — in the applicant\'s language', function (string $lang, string $subject, string $reasonLabel) {
    $mailer = a8Mailer();
    $user = a8User('+994500080803', $lang);
    $app = LegalEntityApplication::query()->create([
        'applicant_user_id' => $user->id, 'complex_name' => 'Gənclik Park', 'legal_name' => 'Gənclik Park MMC', 'voen' => '1234567891',
        'legal_address' => 'Bakı', 'contact_person_name' => 'Leyla Quliyeva', 'contact_phone' => '+994551112233',
        'contact_email' => 'leyla@example.test', 'address' => 'Bakı', 'latitude' => 40.4, 'longitude' => 49.8,
        'apartments_count' => 120, 'status' => 'rejected', 'rejection_reason' => 'VÖEN',
    ]);

    (new SendApplicationRejectedEmailJob((int) $app->id))->handle(app(\App\Domain\Mail\TemplatedMailer::class));

    $mail = $mailer->sent[0];
    expect($mail['subject'])->toBe($subject)
        ->and($mail['body'])->toContain($reasonLabel.' VÖEN')->toContain('Gənclik Park')
        ->and($mail['html'])->toContain('lang="'.$lang.'"');
})->with([
    'az' => ['az', 'Salam Həyətimiz — müraciətiniz təsdiqlənmədi', 'Səbəb:'],
    'ru' => ['ru', 'Salam Həyətimiz — ваша заявка не одобрена', 'Причина:'],
    'en' => ['en', 'Salam Həyətimiz — your application was not approved', 'Reason:'],
]);
