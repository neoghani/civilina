<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('civilina_normative_librarian_documents')) {
            return;
        }

        Schema::table('civilina_normative_librarian_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'canonical_group_key')) {
                $table->string('canonical_group_key', 96)->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'canonical_document_id')) {
                $table->unsignedBigInteger('canonical_document_id')->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'edition_key')) {
                $table->string('edition_key', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'version_status')) {
                $table->string('version_status')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        // Non destructif.
    }
};
