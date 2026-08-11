<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// RBAC — extend admin_users: widen the role enum to the 6 production roles (additive, existing
// super_admin/technical preserved) and add the optional complex scope for complex_manager admins.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE admin_users MODIFY role "
            ."ENUM('super_admin','technical','operator','finance','support','complex_manager') "
            ."NOT NULL DEFAULT 'technical'"
        );

        Schema::table('admin_users', function (Blueprint $table) {
            $table->unsignedBigInteger('complex_id')->nullable()->after('role');
            $table->index('complex_id', 'idx_admin_users_complex');
            $table->foreign('complex_id', 'fk_admin_users_complex_id')
                ->references('id')->on('complexes')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropForeign('fk_admin_users_complex_id');
            $table->dropIndex('idx_admin_users_complex');
            $table->dropColumn('complex_id');
        });

        DB::statement(
            "ALTER TABLE admin_users MODIFY role ENUM('super_admin','technical') NOT NULL DEFAULT 'technical'"
        );
    }
};
