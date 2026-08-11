<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Runtime, admin-tunable settings (R-DOM-09) — key/value, no code change to flip. First use: the global
// `security.require_2fa` toggle for admin login.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();

            $table->unique('key', 'uq_settings_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
