<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One row per built copy of the website. `versions` says which version of each document it holds,
// so going back to a release also knows what counts as published.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_releases', function (Blueprint $table) {
            $table->id();
            $table->string('directory', 60)->unique();
            $table->enum('kind', ['publish', 'rebuild']);
            $table->json('versions');
            $table->unsignedSmallInteger('pages');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_releases');
    }
};
