<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMPLEMENTATION_PLAN M4 (B4). Marks a roster row that exists BECAUSE of a family link (the access layer stays
 * separate from the relationship layer — §4.2). NULL = every existing row (legacy / own access) — unchanged
 * behaviour. The FK to family_links is added in B8 together with that table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_users', function (Blueprint $table) {
            $table->unsignedBigInteger('family_link_id')->nullable()->after('added_by_admin_id');
            $table->index('family_link_id', 'idx_device_users_family_link');
        });
    }

    public function down(): void
    {
        Schema::table('device_users', function (Blueprint $table) {
            $table->dropIndex('idx_device_users_family_link');
            $table->dropColumn('family_link_id');
        });
    }
};
