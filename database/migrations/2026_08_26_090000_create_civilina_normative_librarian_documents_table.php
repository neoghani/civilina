<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('civilina_normative_librarian_documents')) {
            return;
        }

        Schema::create('civilina_normative_librarian_documents', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 64)->unique();
            $table->text('source_path');
            $table->text('relative_path');
            $table->string('filename');
            $table->string('extension', 12)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('sha256', 64)->nullable()->index();
            $table->unsignedBigInteger('duplicate_of_id')->nullable()->index();
            $table->string('source_collection')->nullable()->index();

            $table->string('reference_code')->nullable()->index();
            $table->string('reference_normalized')->nullable()->index();
            $table->string('issuer')->nullable()->index();
            $table->string('category_label')->nullable()->index();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('publication_month')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable()->index();
            $table->string('classification_index')->nullable()->index();
            $table->json('ics_codes')->nullable();

            $table->string('discipline')->nullable()->index();
            $table->string('domain')->nullable()->index();
            $table->string('subdomain')->nullable()->index();

            $table->string('document_kind')->default('standard')->index();
            $table->string('extraction_status')->default('pending')->index();
            $table->string('classification_status')->default('TO_REVIEW')->index();
            $table->decimal('confidence', 5, 2)->default(0)->index();

            $table->longText('cover_text')->nullable();
            $table->text('review_notes')->nullable();
            $table->json('metadata')->nullable();

            $table->unsignedBigInteger('registry_standard_id')->nullable()->index();
            $table->string('publish_status')->default('not_published')->index();
            $table->timestamp('published_at')->nullable();

            $table->timestamp('source_modified_at')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();

            $table->foreign('duplicate_of_id')
                ->references('id')
                ->on('civilina_normative_librarian_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('civilina_normative_librarian_documents');
    }
};
