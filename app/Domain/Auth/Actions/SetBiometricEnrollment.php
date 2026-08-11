<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Services\UserDeviceService;
use App\Domain\Users\Models\User;

/**
 * Mark biometric unlock enrolled/disabled for the calling install (openapi
 * enrollBiometrics / disableBiometrics). Biometric unlock gates every open command
 * (R-SEC-04); this is the per-install opt-in flag.
 */
final class SetBiometricEnrollment
{
    public function __construct(private readonly UserDeviceService $devices) {}

    public function handle(User $user, ?string $fingerprint, bool $enrolled): void
    {
        $device = $this->devices->findByFingerprint((int) $user->getKey(), $fingerprint);

        $device?->forceFill(['biometric_enrolled' => $enrolled])->save();
    }
}
