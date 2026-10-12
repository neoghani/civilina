<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('civilina_normative_deep_documents')) {
            Schema::create('civilina_normative_deep_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('document_id')->unique();
                $table->string('reference_code')->nullable()->index();
                $table->string('edition_key')->nullable()->index();
                $table->string('status')->default('PENDING')->index();
                $table->string('source_kind')->nullable();
                $table->unsignedInteger('char_count')->default(0);
                $table->unsignedInteger('section_count')->default(0);
                $table->unsignedInteger('requirement_candidate_count')->default(0);
                $table->unsignedInteger('tolerance_candidate_count')->default(0);
                $table->longText('raw_text')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('civilina_normative_deep_sections')) {
            Schema::create('civilina_normative_deep_sections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('deep_document_id')->index();
                $table->unsignedBigInteger('document_id')->index();
                $table->string('section_code')->nullable()->index();
                $table->string('heading')->nullable();
                $table->unsignedInteger('level')->default(1);
                $table->unsignedInteger('sequence')->default(0);
                $table->longText('section_text')->nullable();
                $table->string('text_sha256', 64)->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('civilina_normative_deep_requirement_candidates')) {
            Schema::create('civilina_normative_deep_requirement_candidates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('deep_document_id')->index();
                $table->unsignedBigInteger('section_id')->nullable()->index();
                $table->unsignedBigInteger('document_id')->index();
                $table->string('candidate_type')->default('REQUIREMENT')->index();
                $table->string('status')->default('CANDIDATE')->index();
                $table->longText('text');
                $table->string('marker')->nullable();
                $table->string('numeric_value')->nullable();
                $table->string('unit')->nullable();
                $table->string('operator')->nullable();
                $table->decimal('confidence', 5, 2)->default(0);
                $table->string('text_sha256', 64)->index();
                $table->text('validation_note')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('civilina_normative_deep_runs')) {
            Schema::create('civilina_normative_deep_runs', function (Blueprint $table) {
                $table->id();
                $table->string('status')->default('RUNNING')->index();
                $table->unsignedInteger('selected')->default(0);
                $table->unsignedInteger('processed')->default(0);
                $table->unsignedInteger('success')->default(0);
                $table->unsignedInteger('partial')->default(0);
                $table->unsignedInteger('failed')->default(0);
                $table->unsignedInteger('sections')->default(0);
                $table->unsignedInteger('requirements')->default(0);
                $table->unsignedInteger('tolerances')->default(0);
                $table->text('report_path')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Non destructif volontairement.
    }
};
