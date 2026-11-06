<?php

namespace App\Domain\Admin\Settings;

use App\Domain\Admin\Services\SettingsService;
use Closure;

/**
 * Password rules for admin accounts, from Settings › Security. The minimum length never drops below FLOOR,
 * so a setting can tighten the rule but not weaken it.
 */
final class AdminPasswordPolicy
{
    public const FLOOR = 12;

    public function __construct(private readonly SettingsService $settings) {}

    /** @return array<int, mixed> */
    public function rules(): array
    {
        $min = max(self::FLOOR, (int) $this->settings->value('security', 'password_min_length'));
        $rules = ['string', "min:{$min}", 'max:255'];

        foreach ([
            'password_require_uppercase' => ['/\p{Lu}/u', 'validation.password_uppercase'],
            'password_require_number' => ['/\p{N}/u', 'validation.password_number'],
            'password_require_symbol' => ['/[^\p{L}\p{N}\s]/u', 'validation.password_symbol'],
        ] as $key => [$pattern, $message]) {
            if ($this->settings->value('security', $key)) {
                $rules[] = static function (string $attribute, mixed $value, Closure $fail) use ($pattern, $message): void {
                    if (! is_string($value) || preg_match($pattern, $value) !== 1) {
                        $fail(__($message));
                    }
                };
            }
        }

        return $rules;
    }
}
