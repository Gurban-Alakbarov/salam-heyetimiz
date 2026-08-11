<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RBAC §complex scoping — a residential complex (yard) that scopes complex_manager admins and groups
// devices/barriers/residents. New entity (no prior structure existed; regions were too coarse).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complexes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('code', 40);
            $table->unsignedSmallInteger('region_id')->nullable();
            $table->string('address', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code', 'uq_complexes_code');
            $table->index('region_id', 'idx_complexes_region');

            $table->foreign('region_id', 'fk_complexes_region_id')
                ->references('id')->on('regions')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complexes');
    }
};
