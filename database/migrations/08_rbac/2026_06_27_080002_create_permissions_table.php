<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RBAC — the permission catalog. DB-driven so new permissions are pure data (no app-logic change).
// `key` is the stable machine identifier checked by the `perm:` middleware and the SPA.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100);
            $table->string('group', 60);
            $table->string('label', 160);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->unique('key', 'uq_permissions_key');
            $table->index('group', 'idx_permissions_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
