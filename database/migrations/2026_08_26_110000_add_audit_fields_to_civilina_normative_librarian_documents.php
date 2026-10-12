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
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'audit_status')) {
                $table->string('audit_status')->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'audit_risk')) {
                $table->decimal('audit_risk', 5, 2)->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'audit_reason')) {
                $table->text('audit_reason')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'audited_at')) {
                $table->timestamp('audited_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Audit non destructif : on conserve les informations.
    }
};
