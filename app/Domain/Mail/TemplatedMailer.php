<?php

namespace App\Domain\Mail;

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Admin\Settings\RuntimeMailer;
use Illuminate\Contracts\View\Factory as ViewFactory;

/**
 * Generic transactional-email composer. Resolves the subject (Settings, per EmailType), renders the
 * type's Blade view to HTML + builds a plain-text fallback, and hands both to the existing
 * production-ready RuntimeMailer (Brevo SMTP). Every email type flows through here, so a new type is
 * a template + an enum case only. See docs/registration/USER_REGISTRATION_ARCHITECTURE.md §7.
 */
final class TemplatedMailer
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly RuntimeMailer $mailer,
        private readonly ViewFactory $view,
    ) {}

    public function isConfigured(): bool
    {
        return $this->mailer->isConfigured();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \Throwable on transport/send failure (caller reports)
     */
    public function send(EmailType $type, string $to, array $data, string $locale = 'az'): void
    {
        $subject = $this->subject($type);
        $html = $this->htmlEnabled() ? $this->view->make($type->view(), $data + ['subject' => $subject])->render() : null;

        $this->mailer->send($to, $subject, $this->text($data), $html);
    }

    private function subject(EmailType $type): string
    {
        $configured = trim((string) $this->settings->value('email', $type->subjectKey()));

        return $configured !== '' ? $configured : $type->defaultSubject();
    }

    private function htmlEnabled(): bool
    {
        $value = $this->settings->value('email', 'html_enabled');

        return $value === null ? true : (bool) $value;
    }

    /** @param array<string, mixed> $data */
    private function text(array $data): string
    {
        $lines = [];
        if (! empty($data['intro'])) {
            $lines[] = (string) $data['intro'];
        }
        if (isset($data['code'])) {
            $lines[] = 'Kod: '.$data['code'];
        }
        if (isset($data['ttlMinutes'])) {
            $lines[] = 'Kod '.$data['ttlMinutes'].' dəqiqə ərzində etibarlıdır.';
        }
        $lines[] = 'Bu kodu heç kimlə paylaşmayın.';

        return implode("\n", $lines);
    }
}
