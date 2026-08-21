<?php

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Events\DeviceAssigned;
use App\Domain\Payments\Events\OrderPaid;
use App\Domain\Roster\Models\DeviceUser;
use Illuminate\Support\Facades\Event;

/*
| R-DOM-13: on OrderPaid for a device-sale item, the payer is bound as the device owner.
*/

it('binds the payer as owner when a device-sale order is paid', function () {
    Event::fake([DeviceAssigned::class]);

    $payer = makeUser('+994506600001');
    $device = makeUnassignedDevice('SALE-1', '+994701600001');

    $order = makePaidOrder($payer, 13500, 'KB-DEV-1');
    $order->items()->create([
        'item_type' => 'device',
        'referenced_id' => $device->id,
        'description' => 'Device sale',
        'quantity' => 1,
        'unit_amount_minor' => 13500,
        'total_amount_minor' => 13500,
    ]);

    OrderPaid::dispatch($order);

    $device->refresh();
    expect($device->status)->toBe(DeviceStatus::Active)
        ->and((int) $device->owner_user_id)->toBe($payer->id)
        ->and(DeviceUser::query()->where('device_id', $device->id)->where('user_id', $payer->id)->where('role', 'owner')->where('status', 'active')->exists())->toBeTrue();

    Event::assertDispatched(DeviceAssigned::class, fn (DeviceAssigned $e) => $e->via === 'purchase');
});

it('is idempotent — re-delivering OrderPaid does not double-assign', function () {
    $payer = makeUser('+994506600002');
    $device = makeUnassignedDevice('SALE-2', '+994701600002');

    $order = makePaidOrder($payer, 13500, 'KB-DEV-2');
    $order->items()->create([
        'item_type' => 'device',
        'referenced_id' => $device->id,
        'description' => 'Device sale',
        'quantity' => 1,
        'unit_amount_minor' => 13500,
        'total_amount_minor' => 13500,
    ]);

    OrderPaid::dispatch($order);
    OrderPaid::dispatch($order); // replay

    expect(DeviceUser::query()->where('device_id', $device->id)->where('status', 'active')->count())->toBe(1);
});
