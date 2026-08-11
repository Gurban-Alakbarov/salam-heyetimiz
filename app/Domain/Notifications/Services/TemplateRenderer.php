<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Models\NotificationTemplate;
use App\Support\Enums\Locale;

/**
 * Resolves a notification template's localized copy (DB Arch §7.2; INVENTORY §6) and interpolates
 * `{var}` placeholders. Copy lives in `notification_template_locales` (R-LOC-03) — never hardcoded.
 * The locale is the recipient's preferred language; a missing/inactive template or a missing locale
 * row falls back to the default locale (az, R-LOC-01). Returns null when nothing is resolvable so the
 * caller can skip safely.
 */
final class TemplateRenderer
{
    /**
     * @param  array<string, scalar|null>  $variables
     * @return array{title: string, body: string}|null
     */
    public function render(string $templateKey, Locale $locale, array $variables): ?array
    {
        $template = NotificationTemplate::query()
            ->where('template_key', $templateKey)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            return null;
        }

        $row = $template->locales()->where('locale', $locale->value)->first()
            ?? $template->locales()->where('locale', Locale::default()->value)->first();

        if ($row === null) {
            return null;
        }

        return [
            'title' => $this->interpolate((string) ($row->subject ?? ''), $variables),
            'body' => $this->interpolate((string) $row->body, $variables),
        ];
    }

    /** Replace `{name}` tokens from $variables; unknown tokens are left intact. */
    private function interpolate(string $text, array $variables): string
    {
        return preg_replace_callback(
            '/\{(\w+)\}/',
            static fn (array $m): string => array_key_exists($m[1], $variables) ? (string) $variables[$m[1]] : $m[0],
            $text,
        ) ?? $text;
    }
}
