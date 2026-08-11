<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Auto-release for a latching open (barrier wiring). VL110C's `RELAY,1#` LATCHES the relay on, so an
// open that only sends the activate leaves the barrier held open until someone sends `RELAY,0#`.
// Opting in here makes the driver send close_command automatically once the open is CONFIRMED executed.
//
// Value = EXTRA hold (ms) after that confirmation before releasing; 0 = release immediately (the
// contact still stays closed for the release's own round-trip, which is the barrier's trigger pulse).
// NULL = opt out: plain latching open (current behaviour). UMKa stays NULL — its OUTPUT0 already
// self-clears on-device (cmdout.p 1 s), so a software release would be wrong.
//
// NB: the hold is anchored to the confirmation, not the send — RELAY,1#'s speed interlock defers the
// cut by ~10 s when the unit has a GPS fix, and a release sent before it lands cancels the cut.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_models', function (Blueprint $table) {
            $table->unsignedSmallInteger('open_pulse_ms')->nullable()->after('command_result_confirm');
        });
    }

    public function down(): void
    {
        Schema::table('device_models', function (Blueprint $table) {
            $table->dropColumn('open_pulse_ms');
        });
    }
};
