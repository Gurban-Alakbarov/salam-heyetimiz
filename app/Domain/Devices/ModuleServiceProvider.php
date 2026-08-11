<?php

namespace App\Domain\Devices;

use App\Domain\Devices\Listeners\AssignPurchasedDeviceOnOrderPaid;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Observers\DeviceImageObserver;
use App\Domain\Devices\Policies\DevicePolicy;
use App\Domain\Payments\Events\OrderPaid;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Devices (Backend Arch §14.4): device lifecycle, ownership/assignment, status transitions,
 * location/health metadata. Consumes Payments' OrderPaid for the device-sale assignment trigger
 * (R-DOM-13 / R-ARCH-07). Whitelist programming is DeviceComm's reaction to the device events (batch 09).
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Device::class, DevicePolicy::class);

        Device::observe(DeviceImageObserver::class);

        Event::listen(OrderPaid::class, AssignPurchasedDeviceOnOrderPaid::class);
    }
}
