<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMPLEMENTATION_PLAN M7 (B5). Links a back-office admin (in practice a complex_manager = Komendant) to the
 * mobile `users` account they sign in with via the existing email-OTP flow — no second auth system. One mobile
 * account per admin and vice-versa (unique). Nullable: every existing admin stays unlinked (unchanged).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('complex_id');
            $table->unique('user_id', 'uq_admin_users_user_id');
            $table->foreign('user_id', 'fk_admin_users_user_id')
                ->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropForeign('fk_admin_users_user_id');
            $table->dropUnique('uq_admin_users_user_id');
            $table->dropColumn('user_id');
        });
    }
};
