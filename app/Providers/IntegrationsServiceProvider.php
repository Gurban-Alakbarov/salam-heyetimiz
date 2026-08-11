<?php

namespace App\Providers;

use App\Domain\Auth\Adapters\FakeEmailOtpTransport;
use App\Domain\Auth\Adapters\FakeOtpTransport;
use App\Domain\Auth\Adapters\SmsOtpTransport;
use App\Domain\Auth\Adapters\TemplatedEmailOtpTransport;
use App\Domain\Auth\Contracts\EmailOtpTransport;
use App\Domain\Auth\Contracts\OtpTransport;
use App\Domain\DeviceComm\Adapters\FakeDeviceSmsGateway;
use App\Domain\DeviceComm\Adapters\FakeTraccarClient;
use App\Domain\DeviceComm\Adapters\HttpDeviceSmsGateway;
use App\Domain\DeviceComm\Adapters\HttpTraccarClient;
use App\Domain\DeviceComm\Contracts\DeviceSmsGateway;
use App\Domain\DeviceComm\Contracts\TraccarClient;
use App\Domain\Payments\Adapters\BirPay\BirPayGateway;
use App\Domain\Payments\Adapters\FakeKapitalGateway;
use App\Domain\Payments\Adapters\PaymentGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Binds vendor-replaceable integration interfaces to their concrete or fake implementations
 * (Backend Arch §13 / R-ARCH-08). Concrete vs Fake selection keys off the testing environment.
 *
 * Further bindings land with their owning modules (PushClient — batch 08; ObjectStorage — batch 10).
 */
class IntegrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // PaymentGateway (R-PAY): real BirPay gateway in all environments except testing, where the
        // in-memory FakeKapitalGateway is a singleton so tests can drive the bank's responses.
        if ($this->app->environment('testing')) {
            $this->app->singleton(FakeKapitalGateway::class);
            $this->app->alias(FakeKapitalGateway::class, PaymentGateway::class);
        } else {
            $this->app->singleton(PaymentGateway::class, BirPayGateway::class);
        }

        // OTP transport (R-ARCH-08): the in-memory fake in testing / when SMS_PROVIDER=fake;
        // the real AZ SMS client otherwise. The fake is a singleton so tests can read codes back.
        if ($this->app->environment('testing') || config('integrations.sms.provider') === 'fake') {
            $this->app->singleton(FakeOtpTransport::class);
            $this->app->alias(FakeOtpTransport::class, OtpTransport::class);
        } else {
            $this->app->singleton(OtpTransport::class, SmsOtpTransport::class);
        }

        // Email OTP transport: in-memory fake in testing so tests read codes back; the real templated
        // mailer (Brevo SMTP via TemplatedMailer) everywhere else. SMS path above is untouched.
        if ($this->app->environment('testing')) {
            $this->app->singleton(FakeEmailOtpTransport::class);
            $this->app->alias(FakeEmailOtpTransport::class, EmailOtpTransport::class);
        } else {
            $this->app->singleton(EmailOtpTransport::class, TemplatedEmailOtpTransport::class);
        }

        // Traccar client (R-GSM-01, v1.2 primary transport): the in-memory fake in testing / when
        // TRACCAR_DRIVER=fake so tests can drive command outcomes and read sent commands back;
        // the real HTTP client against the Traccar REST API otherwise.
        if ($this->app->environment('testing') || config('integrations.traccar.driver') === 'fake') {
            $this->app->singleton(FakeTraccarClient::class);
            $this->app->alias(FakeTraccarClient::class, TraccarClient::class);
        } else {
            $this->app->singleton(TraccarClient::class, HttpTraccarClient::class);
        }

        // Device SMS gateway (R-GSM-01, v1.2 emergency fallback): in-memory fake in testing / when
        // SMS_PROVIDER=fake so tests can assert dispatched commands; the real AZ SMS client otherwise.
        if ($this->app->environment('testing') || config('integrations.sms.provider') === 'fake') {
            $this->app->singleton(FakeDeviceSmsGateway::class);
            $this->app->alias(FakeDeviceSmsGateway::class, DeviceSmsGateway::class);
        } else {
            $this->app->singleton(DeviceSmsGateway::class, HttpDeviceSmsGateway::class);
        }
    }

    public function boot(): void
    {
        //
    }
}
