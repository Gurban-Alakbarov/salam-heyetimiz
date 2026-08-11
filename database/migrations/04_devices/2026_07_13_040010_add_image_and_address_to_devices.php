<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Barrier presentation fields (mobile device card + admin management):
//   image_url — optional photo of the barrier (URL; upload can be layered on later)
//   address   — optional full street address (distinct from the short location_label)
// Both nullable + additive: existing rows stay NULL, no data loss, clean rollback.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('image_url', 2048)->nullable()->after('location_label');
            $table->string('address', 255)->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'address']);
        });
    }
};
