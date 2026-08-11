<?php

namespace Database\Seeders\Lookups;

use App\Domain\Catalog\Models\DeviceModel;
use Illuminate\Database\Seeder;

/**
 * Initial supported device model (DB Arch §11.1). The definitive model list and
 * firmware capabilities are confirmed in Phase 0; whitelist_capacity defaults to
 * 100 per R-DOM-14 and is adjusted per real hardware by ops.
 */
class DeviceModelSeeder extends Seeder
{
    public function run(): void
    {
        // v1.2 transport pivot — confirmed field hardware (FINAL_PHASE0_VERDICT.md).
        DeviceModel::query()->updateOrCreate(
            ['vendor' => 'GLONASSSoft', 'model_code' => 'UMKa 310 v2L'],
            [
                'supports_clip' => false,
                'supports_sms' => true,
                'supports_mqtt' => false,
                'default_driver_type' => 'traccar', // Wialon IPS/Combine via self-hosted Traccar
                'fallback_open_driver' => 'sms',
                'whitelist_capacity' => 100,
                'sms_open_command' => 'OUTPUT0=1', // UMKa cmd #25 (1 s pulse via on-device cmdout.p)
                'open_command' => 'OUTPUT0=1',     // Phase 4: model-driven command text
                'close_command' => 'OUTPUT0=0',
                'command_result_confirm' => false, // Wialon: actuation confirmed via telemetry output bit (webhook)
                'open_pulse_ms' => null,           // opt out: OUT0 self-clears on-device (cmdout.p 1 s)
                'notes' => 'GLONASSSoft telematics tracker; 1 open-drain output (OUT0). Confirm cmdout.p trigger + Traccar command in Phase-0 (T1).',
                'is_active' => true,
            ],
        );

        // Jimi VL110C (GT06 family) — onboarded via self-hosted Traccar's gt06 decoder.
        // Phase 4 relay: latching RELAY,<SW># command; actuation confirmed by parsing the device's
        // 0x21 response text ("Cut off the fuel supply: Success!" / "Restore fuel supply: Success!")
        // from Traccar's commandResult events (command_result_confirm = true).
        DeviceModel::query()->updateOrCreate(
            ['vendor' => 'Jimi', 'model_code' => 'VL110C'],
            [
                'supports_clip' => false,
                'supports_sms' => true,
                'supports_mqtt' => false,
                'default_driver_type' => 'traccar', // GT06 via self-hosted Traccar
                'fallback_open_driver' => null,     // no SMS relay fallback (online command path)
                'whitelist_capacity' => 100,
                'sms_open_command' => null,
                'open_command' => 'RELAY,1#',       // activate (latch relay on)
                'close_command' => 'RELAY,0#',      // release (latch relay off)
                'command_result_confirm' => true,   // confirm via 0x21 response (commandResult events)
                'open_pulse_ms' => 0,               // barrier: RELAY,1# latches → release as soon as the cut is CONFIRMED
                'notes' => 'Jimi VL110C GT06 barrier controller. Open auto-releases: RELAY,1# → wait for the confirmation → RELAY,0# (releasing earlier cancels the cut; see TraccarDriver).',
                'is_active' => true,
            ],
        );
    }
}
