<?php

use App\Domain\Payments\Enums\OrderItemType;
use App\Domain\Payments\Support\OrderPricing;

it('prices each item type from the settings defaults (minor units)', function () {
    $pricing = new OrderPricing();

    expect($pricing->unitPriceMinor(OrderItemType::Device))->toBe(13500)
        ->and($pricing->unitPriceMinor(OrderItemType::SubMain))->toBe(1200)
        ->and($pricing->unitPriceMinor(OrderItemType::SubAdditional))->toBe(600)
        ->and($pricing->unitPriceMinor(OrderItemType::SubRenewal))->toBe(1200);
});

it('returns a non-empty description for every item type', function () {
    $pricing = new OrderPricing();

    foreach (OrderItemType::cases() as $type) {
        expect($pricing->descriptionFor($type))->not->toBe('');
    }
});
