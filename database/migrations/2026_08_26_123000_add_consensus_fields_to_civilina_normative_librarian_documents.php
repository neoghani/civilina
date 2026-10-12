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
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'consensus_status')) {
                $table->string('consensus_status')->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'consensus_score')) {
                $table->decimal('consensus_score', 5, 2)->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'consensus_reason')) {
                $table->text('consensus_reason')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'consensus_at')) {
                $table->timestamp('consensus_at')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'reviewed_by_user_id')) {
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Non destructif.
    }
};
