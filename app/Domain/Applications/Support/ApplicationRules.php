<?php

namespace App\Domain\Applications\Support;

/**
 * Server-side validation for registration applications (B9). Location = the OpenStreetMap pin as decimal
 * latitude/longitude inside the configured service area; VÖEN = 10 digits; AZ mobile phone format.
 */
final class ApplicationRules
{
    /** @return array<string, mixed> */
    public static function individual(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+994\d{9}$/'],
            'email' => ['required', 'string', 'email:rfc', 'max:160'],
            'address' => ['required', 'string', 'min:3', 'max:255'],
            'region_id' => ['sometimes', 'nullable', 'integer', 'exists:regions,id'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ] + self::location();
    }

    /** @return array<string, mixed> */
    public static function legal(): array
    {
        return [
            'complex_name' => ['required', 'string', 'min:2', 'max:160'],
            'legal_name' => ['required', 'string', 'min:2', 'max:200'],
            'voen' => ['required', 'string', 'regex:/^\d{10}$/'],
            'legal_address' => ['required', 'string', 'min:3', 'max:255'],
            'contact_person_name' => ['required', 'string', 'min:2', 'max:120'],
            'contact_phone' => ['required', 'string', 'regex:/^\+994\d{9}$/'],
            'contact_email' => ['required', 'string', 'email:rfc', 'max:160'],
            'address' => ['required', 'string', 'min:3', 'max:255'],
            'region_id' => ['sometimes', 'nullable', 'integer', 'exists:regions,id'],
            'apartments_count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ] + self::location();
    }

    /** @return array<string, mixed> */
    private static function location(): array
    {
        $a = (array) config('domain.applications.service_area');

        return [
            'latitude' => ['required', 'numeric', 'between:'.$a['lat_min'].','.$a['lat_max']],
            'longitude' => ['required', 'numeric', 'between:'.$a['lng_min'].','.$a['lng_max']],
        ];
    }
}
