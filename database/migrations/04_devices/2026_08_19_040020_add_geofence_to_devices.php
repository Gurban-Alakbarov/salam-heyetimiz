<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// GEOFENCE-1 D1 — per-device distance restriction. geofence_enabled defaults to false so every
// existing device is behaviourally unchanged (no location required, open flow identical). radius is
// only meaningful when enabled; the open-time check + config action enforce coords + radius presence.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('geofence_enabled')->default(false)->after('longitude');
            $table->unsignedSmallInteger('geofence_radius_m')->nullable()->after('geofence_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['geofence_enabled', 'geofence_radius_m']);
        });
    }
};
