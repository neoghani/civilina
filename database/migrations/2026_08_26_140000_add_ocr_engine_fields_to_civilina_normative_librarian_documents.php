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
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'ocr_status')) {
                $table->string('ocr_status')->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'ocr_engine')) {
                $table->string('ocr_engine')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'ocr_language')) {
                $table->string('ocr_language')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'ocr_pages')) {
                $table->unsignedInteger('ocr_pages')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'ocr_chars')) {
                $table->unsignedInteger('ocr_chars')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'ocr_text')) {
                $table->longText('ocr_text')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'ocr_last_error')) {
                $table->text('ocr_last_error')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_librarian_documents', 'ocr_at')) {
                $table->timestamp('ocr_at')->nullable()->index();
            }
        });

        if (! Schema::hasTable('civilina_normative_ocr_runs')) {
            Schema::create('civilina_normative_ocr_runs', function (Blueprint $table) {
                $table->id();
                $table->string('status')->default('RUNNING')->index();
                $table->unsignedInteger('requested_limit')->nullable();
                $table->unsignedInteger('max_pages')->default(2);
                $table->unsignedInteger('dpi')->default(220);
                $table->string('engine')->nullable();
                $table->string('language')->nullable();
                $table->unsignedInteger('eligible')->default(0);
                $table->unsignedInteger('processed')->default(0);
                $table->unsignedInteger('success')->default(0);
                $table->unsignedInteger('failed')->default(0);
                $table->unsignedInteger('skipped')->default(0);
                $table->text('report_path')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('civilina_normative_ocr_pages')) {
            Schema::create('civilina_normative_ocr_pages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('document_id')->index();
                $table->unsignedInteger('page_number');
                $table->longText('text')->nullable();
                $table->unsignedInteger('char_count')->default(0);
                $table->string('engine')->nullable();
                $table->string('language')->nullable();
                $table->unsignedInteger('dpi')->nullable();
                $table->string('text_sha256', 64)->nullable()->index();
                $table->timestamps();

                $table->unique(['document_id', 'page_number']);
            });
        }
    }

    public function down(): void
    {
        // Non destructif : les résultats OCR restent auditables.
    }
};
