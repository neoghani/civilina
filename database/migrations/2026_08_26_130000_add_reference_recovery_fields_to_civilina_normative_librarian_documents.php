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
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'reference_recovery_status')) {
                $table->string('reference_recovery_status')->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'reference_recovery_score')) {
                $table->decimal('reference_recovery_score', 5, 2)->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'reference_recovery_reason')) {
                $table->text('reference_recovery_reason')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'reference_recovered_at')) {
                $table->timestamp('reference_recovered_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Non destructif.
    }
};
