<?php

use App\Http\Api\V1\Controllers\Auth\AuthController;
use App\Http\Api\V1\Controllers\Auth\BiometricController;
use App\Http\Api\V1\Controllers\Commands\OpenCommandController;
use App\Http\Api\V1\Controllers\Devices\DeviceController;
use App\Http\Api\V1\Controllers\Devices\DeviceStatsController;
use App\Http\Api\V1\Controllers\Devices\TechDeviceController;
use App\Http\Api\V1\Controllers\Auth\RegistrationController;
use App\Http\Api\V1\Controllers\BootstrapController;
use App\Http\Api\V1\Controllers\Health\HealthController;
use App\Http\Api\V1\Controllers\Notifications\NotificationController;
use App\Http\Api\V1\Controllers\Notifications\PushTokenController;
use App\Http\Api\V1\Controllers\Orders\OrderController;
use App\Http\Api\V1\Controllers\Payments\PaymentReturnController;
use App\Http\Api\V1\Controllers\Subscriptions\SubscriptionController;
use App\Http\Api\V1\Controllers\Visitor\VisitorAccessController;
use App\Http\Api\V1\Controllers\Visitor\VisitorLinkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile / public API — prefix /v1 (group "api")
|--------------------------------------------------------------------------
| Route names mirror OpenAPI operationIds (R-API-02). The mobile JWT guard
| (auth:auth.user) lands with the Auth module (batch 07); the public auth + health
| routes carry no guard, everything else requires a valid mobile access token.
*/

Route::get('health/live', [HealthController::class, 'live'])->name('getHealthLive');
Route::get('health/ready', [HealthController::class, 'ready'])->name('getHealthReady');

// Guest app bootstrap (one-shot config for a logged-out client) — public, rate-limited.
Route::get('bootstrap', [BootstrapController::class, 'guest'])->middleware('throttle:public')->name('guestBootstrap');

// Payment return (browser redirect-back from the BirPay hosted page) — public; verifies via getOrderStatus.
Route::get('payments/return', PaymentReturnController::class)->name('paymentReturn');

// ---- Public visitor access (no guard; the path token is the credential) — used by the /v/{token} page ----
// The barrier is opened through the SAME relay pipeline as the mobile app; opens are rate-limited per token+IP.
Route::prefix('visit')->group(function (): void {
    Route::get('{token}', [VisitorAccessController::class, 'show'])->middleware('throttle:public')->name('visitorLinkStatus');
    Route::post('{token}/open', [VisitorAccessController::class, 'open'])->middleware('throttle:visitor-open')->name('visitorLinkOpen');
    Route::get('{token}/command/{commandId}', [VisitorAccessController::class, 'command'])->whereNumber('commandId')->middleware('throttle:public')->name('visitorLinkCommand');
})->where('token', '[A-Za-z0-9_-]+');

// ---- Public auth (batch 07) — no guard; rate-limited per R-SEC-16 ----
Route::post('auth/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:otp-request')->name('requestOtp');
Route::post('auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify')->name('verifyOtp');
Route::post('auth/refresh', [AuthController::class, 'refresh'])->middleware('throttle:public')->name('refreshToken');

// ---- Email-OTP registration + login (unified envelope) — public; rate-limited per R-SEC-16 ----
Route::post('auth/register', [RegistrationController::class, 'register'])->middleware('throttle:register')->name('register');
Route::post('auth/verify-email', [RegistrationController::class, 'verifyEmail'])->middleware('throttle:otp-verify-email')->name('verifyEmail');
Route::post('auth/resend-otp', [RegistrationController::class, 'resendOtp'])->middleware('throttle:otp-resend')->name('resendOtp');
Route::post('auth/login', [RegistrationController::class, 'login'])->middleware('throttle:email-login')->name('emailLogin');

// ---- Authenticated mobile (mobile JWT guard) ----
Route::middleware(['auth:user', 'throttle:mobile'])->group(function (): void {
    // Auth session (batch 07)
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('logout');

    // Current user + authed bootstrap (one-shot — user, devices, subscriptions, app config, flags).
    Route::get('me', [BootstrapController::class, 'me'])->name('currentUser');

    Route::post('me/biometrics/enroll', [BiometricController::class, 'enroll'])->name('enrollBiometrics');
    Route::delete('me/biometrics', [BiometricController::class, 'disable'])->name('disableBiometrics');

    // Push token registration/de-registration for the current install (batch 11 — Notifications).
    Route::put('notifications/push-token', [PushTokenController::class, 'upsert'])->name('upsertPushToken');
    Route::delete('notifications/push-token', [PushTokenController::class, 'destroy'])->name('deletePushToken');

    // In-app notification inbox (batch 11 — Notifications). read-all before {notificationId}/read.
    Route::get('notifications', [NotificationController::class, 'index'])->name('listNotifications');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('markAllNotificationsRead');
    Route::post('notifications/{notificationId}/read', [NotificationController::class, 'read'])->whereNumber('notificationId')->name('markNotificationRead');

    // Orders & Payments (batch 05)
    Route::prefix('orders')->group(function (): void {
        Route::get('/', [OrderController::class, 'index'])->name('listMyOrders');
        Route::post('/', [OrderController::class, 'store'])->name('createOrder');
        Route::get('/{orderId}', [OrderController::class, 'show'])->whereNumber('orderId')->name('getOrder');
        Route::post('/{orderId}/recheck', [OrderController::class, 'recheck'])->whereNumber('orderId')->name('recheckOrder');
    });

    // Subscriptions (batch 06)
    Route::prefix('subscriptions')->group(function (): void {
        Route::get('/', [SubscriptionController::class, 'index'])->name('listMySubscriptions');
        Route::get('/{subscriptionId}', [SubscriptionController::class, 'show'])->whereNumber('subscriptionId')->name('getSubscription');
        Route::post('/{subscriptionId}/renew', [SubscriptionController::class, 'renew'])->whereNumber('subscriptionId')->name('renewSubscription');
        Route::patch('/{subscriptionId}/auto-renew', [SubscriptionController::class, 'toggleAutoRenew'])->whereNumber('subscriptionId')->name('toggleAutoRenew');
    });

    // Devices — user-facing (batch 08)
    Route::prefix('devices')->group(function (): void {
        Route::get('/', [DeviceController::class, 'index'])->name('listMyDevices');
        Route::get('/{deviceId}', [DeviceController::class, 'show'])->whereNumber('deviceId')->name('getDevice');

        // GEOFENCE-1 D5 — the device OWNER toggles their device's distance restriction (owner-only,
        // enforced by the DevicePolicy `configure` ability; geofence fields only, never coordinates).
        Route::patch('/{deviceId}/geofence', [DeviceController::class, 'updateGeofence'])->whereNumber('deviceId')->name('updateDeviceGeofence');

        // DeviceComm core (batch 09-A)
        Route::post('/{deviceId}/open', [OpenCommandController::class, 'open'])->whereNumber('deviceId')->middleware('throttle:open')->name('openDevice');
        Route::get('/{deviceId}/commands', [OpenCommandController::class, 'index'])->whereNumber('deviceId')->name('listDeviceCommands');
        Route::get('/{deviceId}/stats', [DeviceStatsController::class, 'show'])->whereNumber('deviceId')->name('getDeviceStats');

        // Visitor links — a resident shares access they hold (create/list own links for this device).
        Route::post('/{deviceId}/visitor-links', [VisitorLinkController::class, 'store'])->whereNumber('deviceId')->name('createVisitorLink');
        Route::get('/{deviceId}/visitor-links', [VisitorLinkController::class, 'index'])->whereNumber('deviceId')->name('listVisitorLinks');
    });

    // Revoke one of the caller's own visitor links.
    Route::post('visitor-links/{id}/revoke', [VisitorLinkController::class, 'revoke'])->whereNumber('id')->name('revokeVisitorLink');

    // Open commands — status polling + actuation feedback (batch 09-A)
    Route::get('commands/{commandId}', [OpenCommandController::class, 'show'])->whereNumber('commandId')->name('getCommand');
    Route::post('commands/{commandId}/feedback', [OpenCommandController::class, 'feedback'])->whereNumber('commandId')->name('submitOpenFeedback');
});

// ---- Technical mobile mode (admin JWT on the mobile host) — Devices (batch 08) ----
Route::middleware(['auth:admin', 'throttle:admin'])->prefix('technical/devices')->group(function (): void {
    Route::post('/', [TechDeviceController::class, 'register'])->name('techRegisterDevice');
    Route::post('/{deviceId}/assign', [TechDeviceController::class, 'assign'])->whereNumber('deviceId')->name('techAssignDevice');
});
