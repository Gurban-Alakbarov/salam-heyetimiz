<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §2.1 — sim_operators
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sim_operators', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 20);
            $table->string('name', 60);
            $table->char('country_iso', 2)->default('AZ');
            $table->string('mcc_mnc', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code', 'uq_sim_operators_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_operators');
    }
};
