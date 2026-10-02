<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMPLEMENTATION_PLAN M5 (B2). Additive only:
 *  - ownership_mode: `private` (DEFAULT — every existing device keeps the single-owner model unchanged)
 *    or `complex` (residential-complex device; behaviour arrives in B4).
 *  - sale_price_minor (+ who/when recorded): the ONE-OFF device sale price, an admin record only — it is
 *    never used as a subscription price and no in-app payment uses it (BR-20).
 * No backfill needed: the default makes all existing rows `private`; sale columns stay NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->enum('ownership_mode', ['private', 'complex'])->default('private')->after('owner_user_id');
            $table->unsignedInteger('sale_price_minor')->nullable()->after('ownership_mode');
            $table->timestamp('sale_recorded_at')->nullable()->after('sale_price_minor');
            $table->unsignedBigInteger('sale_recorded_by_admin_id')->nullable()->after('sale_recorded_at');

            $table->index('ownership_mode', 'idx_devices_ownership_mode');
            $table->foreign('sale_recorded_by_admin_id', 'fk_devices_sale_recorded_by_admin_id')
                ->references('id')->on('admin_users')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign('fk_devices_sale_recorded_by_admin_id');
            $table->dropIndex('idx_devices_ownership_mode');
            $table->dropColumn(['ownership_mode', 'sale_price_minor', 'sale_recorded_at', 'sale_recorded_by_admin_id']);
        });
    }
};
