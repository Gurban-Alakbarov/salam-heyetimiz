<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A special subscription price for one person, device or complex, set in the admin panel. Without a row the
// price from Ayarlar › Abunəliklər applies. subject_id points at users, devices or complexes by scope.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_price_overrides', function (Blueprint $table) {
            $table->id();
            $table->enum('scope', ['user', 'device', 'complex']);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedInteger('price_minor');
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'subject_id'], 'uq_subscription_price_override_subject');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_price_overrides');
    }
};
