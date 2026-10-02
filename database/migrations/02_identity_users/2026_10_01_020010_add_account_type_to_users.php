<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMPLEMENTATION_PLAN M9 (B9). Physical vs legal person account. NULLABLE on purpose: every existing user
 * keeps NULL (= legacy, behaves exactly as before) — no backfill. Set at registration (optional) or by the
 * first registration application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_type', ['physical', 'legal'])->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_type');
        });
    }
};
