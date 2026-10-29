<?php

use App\Domain\Payments\Enums\OrderItemType;
use App\Domain\Payments\Support\OrderPricing;

it('prices each item type from the settings defaults (minor units)', function () {
    $pricing = new OrderPricing();

    expect($pricing->unitPriceMinor(OrderItemType::Device))->toBe(13500)
        ->and($pricing->unitPriceMinor(OrderItemType::SubMain))->toBe(1500)
        ->and($pricing->unitPriceMinor(OrderItemType::SubAdditional))->toBe(1500) // B2: family member = 15 AZN, same as resident
        ->and($pricing->unitPriceMinor(OrderItemType::SubRenewal))->toBe(1500);
});

it('returns a non-empty description for every item type', function () {
    $pricing = new OrderPricing();

    foreach (OrderItemType::cases() as $type) {
        expect($pricing->descriptionFor($type))->not->toBe('');
    }
});
