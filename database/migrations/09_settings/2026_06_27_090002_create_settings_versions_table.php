<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings version history (R-DOM-09). Every settings mutation (group update / import / restore) appends an
 * immutable snapshot of the full key→value map plus the redacted diff and actor metadata, enabling View
 * History / Compare / Restore and disaster recovery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('reason', 20);                 // update | import | restore
            $table->string('scope_group', 40)->nullable(); // which group (null = all)
            $table->json('snapshot');                      // full key→stored-value map (secrets as ciphertext)
            $table->json('changes')->nullable();           // [{key, old, new}] — secrets redacted
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->string('actor_label', 160)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings_versions');
    }
};
