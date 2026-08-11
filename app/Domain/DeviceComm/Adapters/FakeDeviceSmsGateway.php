<?php

namespace App\Domain\DeviceComm\Adapters;

use App\Domain\DeviceComm\Contracts\DeviceSmsGateway;

/** Test/dev device-SMS gateway (R-ARCH-08): records the last command per phone; always succeeds. */
final class FakeDeviceSmsGateway implements DeviceSmsGateway
{
    /** @var array<string, string> phone => last command */
    public array $sent = [];

    public function sendCommand(string $phone, string $command): bool
    {
        $this->sent[$phone] = $command;

        return true;
    }
}
