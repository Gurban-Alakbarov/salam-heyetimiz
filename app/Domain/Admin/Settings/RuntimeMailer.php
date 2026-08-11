<?php

namespace App\Domain\Admin\Settings;

use App\Domain\Admin\Services\SettingsService;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends mail through an SMTP transport built at runtime from the DB Email settings — the app ships no
 * config/mail.php, so this is the single, real mail path used by "Send Test Email" / "Send Test OTP".
 * It does NOT integrate any external provider; it just uses the operator-configured SMTP server.
 */
class RuntimeMailer
{
    public function __construct(private readonly SettingsService $settings) {}

    public function isConfigured(): bool
    {
        return $this->str('smtp_host') !== '' && $this->str('from_email') !== '';
    }

    /**
     * Send a message. `$html` is optional and additive: when provided the message carries both a
     * plain-text part (`$body`) and an HTML part; existing plain-text callers pass three args and are
     * unaffected.
     *
     * @throws \Throwable on transport/send failure (caller reports the message)
     */
    public function send(string $to, string $subject, string $body, ?string $html = null): void
    {
        $host = $this->str('smtp_host');
        $port = (int) $this->settings->value('email', 'smtp_port') ?: 587;
        $encryption = $this->str('smtp_encryption');

        // smtps (implicit TLS) on 465; STARTTLS is auto-negotiated for 'tls' on 587.
        $transport = new EsmtpTransport($host, $port, $encryption === 'ssl');
        if (($u = $this->str('smtp_username')) !== '') {
            $transport->setUsername($u);
        }
        if (($p = $this->str('smtp_password')) !== '') {
            $transport->setPassword($p);
        }

        $email = (new Email)
            ->from(new Address($this->str('from_email'), $this->str('from_name')))
            ->to($to)
            ->subject($subject)
            ->text($body);
        if ($html !== null && $html !== '') {
            $email->html($html);
        }
        if (($replyTo = $this->str('reply_to_email')) !== '') {
            $email->replyTo($replyTo);
        }

        (new Mailer($transport))->send($email);
    }

    private function str(string $key): string
    {
        return (string) $this->settings->value('email', $key);
    }
}
