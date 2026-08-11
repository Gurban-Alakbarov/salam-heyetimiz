<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RBAC — role→permission assignment. `role` is the AdminRole enum value (string), kept in-column for
// backward compatibility; assignments are pure data (seed/admin-managed) so authorization logic never
// changes when roles/permissions are added. super_admin is implicitly all (not stored).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 40);
            $table->unsignedBigInteger('permission_id');
            $table->timestamps();

            $table->unique(['role', 'permission_id'], 'uq_role_permission');
            $table->index('role', 'idx_role_permissions_role');

            $table->foreign('permission_id', 'fk_role_permissions_permission_id')
                ->references('id')->on('permissions')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
