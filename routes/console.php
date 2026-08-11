<?php

use App\Domain\DeviceComm\Jobs\ExpireStaleOpenCommandsJob;
use App\Domain\DeviceComm\Jobs\WhitelistSyncJob;
use App\Domain\Payments\Jobs\PaymentLogsScannerJob;
use Illuminate\Support\Facades\Schedule;

$scheduleTimezone = config('app.schedule_timezone', 'Asia/Baku');

/*
|--------------------------------------------------------------------------
| Console scheduling (Backend Arch §10)
|--------------------------------------------------------------------------
| Human-time jobs run in Asia/Baku (config app.schedule_timezone); all declare
| ->withoutOverlapping()->onOneServer().
*/

// Payments (batch 05)
Schedule::command('payments:reconcile-orders')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new PaymentLogsScannerJob())
    ->dailyAt('03:30')
    ->onOneServer();

// Subscriptions (batch 06)
Schedule::command('subscriptions:send-renewal-reminders')
    ->dailyAt('01:00')
    ->timezone($scheduleTimezone)
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('subscriptions:expire')
    ->dailyAt('02:00')
    ->timezone($scheduleTimezone)
    ->withoutOverlapping()
    ->onOneServer();

// DeviceComm (batch 09-A) — expire stale queued/dispatching open commands (lifecycle safety net)
Schedule::job(new ExpireStaleOpenCommandsJob())
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// DeviceComm (batch 09-B) — drain the whitelist/provisioning outbox via the resolved driver (R-GSM-07)
Schedule::job(new WhitelistSyncJob())
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Auth (batch 07)
Schedule::command('auth:prune')
    ->dailyAt('03:00')
    ->timezone($scheduleTimezone)
    ->withoutOverlapping()
    ->onOneServer();
