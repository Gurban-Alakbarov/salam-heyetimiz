<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §2.3 — regions (self-referencing district → city).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 20);
            $table->string('name', 80);
            $table->unsignedSmallInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code', 'uq_regions_code');
            $table->index('parent_id', 'idx_regions_parent');

            $table->foreign('parent_id', 'fk_regions_parent_id')
                ->references('id')->on('regions')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
