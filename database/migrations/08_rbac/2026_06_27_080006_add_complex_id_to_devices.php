<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RBAC — link a device (barrier) to its complex so complex_manager admins are scoped to their own
// complex's devices/barriers/residents. Nullable + additive; existing devices stay unscoped until set.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedBigInteger('complex_id')->nullable()->after('region_id');
            $table->index('complex_id', 'idx_devices_complex');
            $table->foreign('complex_id', 'fk_devices_complex_id')
                ->references('id')->on('complexes')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign('fk_devices_complex_id');
            $table->dropIndex('idx_devices_complex');
            $table->dropColumn('complex_id');
        });
    }
};
