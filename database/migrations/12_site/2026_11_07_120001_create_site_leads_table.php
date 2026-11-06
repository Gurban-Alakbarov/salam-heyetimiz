<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Requests sent through the contact form on the public website.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('phone', 40);
            $table->string('place', 80);
            $table->string('topic', 80)->nullable();
            $table->text('message')->nullable();
            $table->char('locale', 2)->default('az');
            $table->enum('status', ['new', 'contacted', 'closed'])->default('new');
            $table->string('admin_note', 1000)->nullable();
            $table->unsignedBigInteger('handled_by_admin_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'id'], 'idx_site_leads_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_leads');
    }
};
