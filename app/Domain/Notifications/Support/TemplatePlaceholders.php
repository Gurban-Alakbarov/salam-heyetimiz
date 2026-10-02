<?php

namespace App\Domain\Notifications\Support;

/**
 * Placeholder whitelist for the template editor (IMPLEMENTATION_PLAN §17 / B10): exactly the `{var}` tokens
 * the code that dispatches each template passes to TemplateRenderer — an unknown token would reach users
 * verbatim, so it is refused (422). Subscription copy is static (no variables — LOCKED DECISION 1).
 * `system.admin_campaign` is read-only: its copy is supplied per campaign, never from locale rows.
 */
final class TemplatePlaceholders
{
    /** template_key => [placeholder => sample value used by preview] */
    private const MAP = [
        'device.opened' => ['visitor_name' => 'Kuryer', 'device_label' => 'Əsas darvaza'],
    ];

    private const READ_ONLY = ['system.admin_campaign'];

    /** @return array<int, string> */
    public static function allowed(string $templateKey): array
    {
        return array_keys(self::MAP[$templateKey] ?? []);
    }

    /** @return array<string, string> */
    public static function samples(string $templateKey): array
    {
        return self::MAP[$templateKey] ?? [];
    }

    public static function isReadOnly(string $templateKey): bool
    {
        return in_array($templateKey, self::READ_ONLY, true);
    }

    /**
     * Tokens in $text that are not allowed for the template.
     *
     * @return array<int, string>
     */
    public static function unknown(string $templateKey, string $text): array
    {
        preg_match_all('/\{(\w+)\}/', $text, $m);

        return array_values(array_unique(array_diff($m[1], self::allowed($templateKey))));
    }
}
