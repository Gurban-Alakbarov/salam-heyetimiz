<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMPLEMENTATION_PLAN M6 (B4). Complex map location (OpenStreetMap picker — B11/B14) and the originating
 * legal-entity application (FK added in B9 with that table). All nullable — existing complexes unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complexes', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedBigInteger('legal_entity_application_id')->nullable()->after('longitude');
            $table->index('legal_entity_application_id', 'idx_complexes_legal_application');
        });
    }

    public function down(): void
    {
        Schema::table('complexes', function (Blueprint $table) {
            $table->dropIndex('idx_complexes_legal_application');
            $table->dropColumn(['latitude', 'longitude', 'legal_entity_application_id']);
        });
    }
};
