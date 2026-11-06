<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The website's editable content. One document per page, plus "site" for the shared texts. `content` is the
// working draft; `published_version` says which revision the live website was built from.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_documents', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->json('content');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('published_version')->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
        });

        // One row per saved version, so any earlier state can be seen and restored.
        Schema::create('site_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_document_id')->constrained('site_documents')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('content');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->unsignedInteger('restored_from')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['site_document_id', 'version'], 'uq_site_revisions_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_revisions');
        Schema::dropIfExists('site_documents');
    }
};
