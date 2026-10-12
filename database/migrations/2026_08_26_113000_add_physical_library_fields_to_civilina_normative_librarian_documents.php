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
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'organized_bucket')) {
                $table->string('organized_bucket')->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'organized_path')) {
                $table->text('organized_path')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'organized_mode')) {
                $table->string('organized_mode')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'organized_at')) {
                $table->timestamp('organized_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Non destructif.
    }
};
