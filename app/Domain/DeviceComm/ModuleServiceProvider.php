<?php

namespace App\Domain\DeviceComm;

use App\Domain\Devices\Events\DeviceAssigned;
use App\Domain\Devices\Events\DeviceDecommissioned;
use App\Domain\Devices\Events\DeviceRegistered;
use App\Domain\Devices\Events\DeviceTransferred;
use App\Domain\DeviceComm\Drivers\SmsDriver;
use App\Domain\DeviceComm\Drivers\TraccarDriver;
use App\Domain\DeviceComm\Listeners\AddOwnerToWhitelistOnDeviceAssigned;
use App\Domain\DeviceComm\Listeners\AddUserToWhitelistOnRosterUserAdded;
use App\Domain\DeviceComm\Listeners\ClearWhitelistOnDeviceDecommissioned;
use App\Domain\DeviceComm\Listeners\RegisterDeviceInTraccar;
use App\Domain\DeviceComm\Listeners\RemoveUserFromWhitelistOnRosterUserRemoved;
use App\Domain\DeviceComm\Listeners\ReprogramWhitelistOnDeviceTransferred;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\DeviceComm\Policies\OpenCommandPolicy;
use App\Domain\Roster\Events\RosterUserAdded;
use App\Domain\Roster\Events\RosterUserRemoved;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Device communication core (Backend Arch §14.6; Tech Spec §12): open-command lifecycle, cooldowns,
 * the whitelist outbox, and command/stats reads. Consumes the Devices lifecycle events (R-ARCH-07):
 * registration provisions the unit in Traccar (without it Traccar rejects the device as an unknown
 * uniqueId and it never comes online), and the ownership events keep the whitelist outbox in sync
 * (R-GSM-07). The transport drivers (Traccar primary, SMS
 * fallback — v1.2) bind here keyed `device-driver.<type>` so DriverResolver resolves them by
 * DriverType; the `device-driver.ble` slot is reserved/deferred (R-GSM-01).
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // DeviceDriver implementations, keyed by DriverType value (R-GSM-01). DriverResolver resolves
        // `device-driver.{type}` from the container; constructor dependencies (TraccarClient /
        // DeviceSmsGateway / mapper / clock) are autowired.
        $this->app->bind('device-driver.traccar', TraccarDriver::class);
        $this->app->bind('device-driver.sms', SmsDriver::class);
    }

    public function boot(): void
    {
        Gate::policy(OpenCommand::class, OpenCommandPolicy::class);

        // A device is useless until Traccar knows its uniqueId — provision it the moment it is created.
        Event::listen(DeviceRegistered::class, RegisterDeviceInTraccar::class);
        Event::listen(DeviceAssigned::class, AddOwnerToWhitelistOnDeviceAssigned::class);
        Event::listen(DeviceTransferred::class, ReprogramWhitelistOnDeviceTransferred::class);
        Event::listen(DeviceDecommissioned::class, ClearWhitelistOnDeviceDecommissioned::class);
        Event::listen(RosterUserAdded::class, AddUserToWhitelistOnRosterUserAdded::class);
        Event::listen(RosterUserRemoved::class, RemoveUserFromWhitelistOnRosterUserRemoved::class);
    }
}
