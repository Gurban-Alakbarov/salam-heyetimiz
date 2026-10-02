<?php

use App\Domain\Payments\Adapters\UnavailablePaymentGateway;
use App\Domain\Payments\Exceptions\PaymentProviderUnavailableException;
use App\Domain\Payments\Support\PaymentGatewayMode;

/*
| IMPLEMENTATION_PLAN B1 — gateway selection + production guard (BR-16). Pure decision function, so the
| production rules are proven without booting a production environment.
*/

it('always uses the fake gateway in the testing environment', function () {
    expect(PaymentGatewayMode::decide('testing', ['gateway' => 'birpay']))->toBe(PaymentGatewayMode::FAKE);
});

it('keeps the real BirPay gateway by default (no config change = unchanged behaviour)', function () {
    expect(PaymentGatewayMode::decide('production', []))->toBe(PaymentGatewayMode::BIRPAY)
        ->and(PaymentGatewayMode::decide('local', ['gateway' => 'birpay', 'fake_enabled' => true]))->toBe(PaymentGatewayMode::BIRPAY);
});

it('requires the fake_enabled feature flag before the fake gateway is used', function () {
    expect(PaymentGatewayMode::decide('local', ['gateway' => 'fake', 'fake_enabled' => false]))->toBe(PaymentGatewayMode::DISABLED)
        ->and(PaymentGatewayMode::decide('local', ['gateway' => 'fake', 'fake_enabled' => true]))->toBe(PaymentGatewayMode::FAKE)
        ->and(PaymentGatewayMode::decide('staging', ['gateway' => 'fake', 'fake_enabled' => true]))->toBe(PaymentGatewayMode::FAKE);
});

it('keeps fake payments OFF in production unless allow_fake_in_production is also set', function () {
    expect(PaymentGatewayMode::decide('production', ['gateway' => 'fake', 'fake_enabled' => true]))->toBe(PaymentGatewayMode::DISABLED)
        ->and(PaymentGatewayMode::decide('production', ['gateway' => 'fake', 'fake_enabled' => false, 'allow_fake_in_production' => true]))->toBe(PaymentGatewayMode::DISABLED)
        ->and(PaymentGatewayMode::decide('production', ['gateway' => 'fake', 'fake_enabled' => true, 'allow_fake_in_production' => true]))->toBe(PaymentGatewayMode::FAKE);
});

it('ships the safe defaults in config (birpay, fake disabled, production fake disallowed)', function () {
    $cfg = require base_path('config/domain/payments.php');

    expect($cfg['gateway'])->toBe('birpay')
        ->and($cfg['fake_enabled'])->toBeFalse()
        ->and($cfg['allow_fake_in_production'])->toBeFalse();
});

it('a requested-but-not-permitted fake gateway is unavailable instead of falling through to the bank', function () {
    $gateway = new UnavailablePaymentGateway();

    expect(fn () => $gateway->getOrderStatus('x'))->toThrow(PaymentProviderUnavailableException::class)
        ->and(fn () => $gateway->cancel('x'))->toThrow(PaymentProviderUnavailableException::class)
        ->and(fn () => $gateway->refund('x', 100, 'k'))->toThrow(PaymentProviderUnavailableException::class);
});
