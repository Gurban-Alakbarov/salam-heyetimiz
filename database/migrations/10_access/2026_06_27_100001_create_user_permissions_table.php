<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-user permission overrides on top of the role template. effect=grant adds a permission the role
// lacks; effect=revoke removes one the role grants. Effective = (role ∪ grants) \ revokes. One row per
// (admin, permission); flipping grant↔revoke replaces the row.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id');
            $table->unsignedBigInteger('permission_id');
            $table->enum('effect', ['grant', 'revoke']);
            $table->unsignedBigInteger('granted_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique(['admin_user_id', 'permission_id'], 'uq_user_permission');
            $table->index('admin_user_id', 'idx_user_permissions_admin');

            $table->foreign('admin_user_id', 'fk_user_permissions_admin')
                ->references('id')->on('admin_users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('permission_id', 'fk_user_permissions_permission')
                ->references('id')->on('permissions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('granted_by_admin_id', 'fk_user_permissions_granter')
                ->references('id')->on('admin_users')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};
