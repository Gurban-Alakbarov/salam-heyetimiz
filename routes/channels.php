<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channels (Reverb) — Tech Spec §6.4
|--------------------------------------------------------------------------
| private-user.{userId}   — user-targeted (command status, subscription notice)
| private-device.{deviceId} — device events (owner subscribes to owned devices)
|
| Authorization callbacks (a user may only subscribe to their own channel; an
| owner only to owned devices) are added with the DeviceComm / realtime increment.
*/

// Channel authorizations added per increment, e.g.:
// Broadcast::channel('user.{userId}', fn ($user, int $userId) => (int) $user->id === $userId);
