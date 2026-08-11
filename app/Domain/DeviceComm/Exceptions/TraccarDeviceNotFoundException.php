<?php

namespace App\Domain\DeviceComm\Exceptions;

use RuntimeException;

/**
 * Thrown by the reconciliation flow when the requested uniqueId has no matching device in Traccar.
 * Reconciliation ADOPTS an existing Traccar device — it never creates one — so a missing Traccar device
 * is a hard stop, not a cue to provision.
 */
final class TraccarDeviceNotFoundException extends RuntimeException
{
    public function __construct(public readonly string $uniqueId)
    {
        parent::__construct(
            "No Traccar device exists with uniqueId [{$uniqueId}]. Reconciliation adopts an existing Traccar "
            .'device only; it never creates one. Register the device in Traccar first, or use the normal '
            .'Laravel→Traccar provisioning path.'
        );
    }
}
