<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('engineering_normative_standards')) {
            Schema::create('engineering_normative_standards', function (Blueprint $table) {
                $table->id();
                $table->string('canonical_code')->nullable()->index();
                $table->string('normalized_code')->nullable()->unique();
                $table->string('family_code')->nullable()->index();
                $table->string('title')->nullable();
                $table->string('issuing_body')->nullable();
                $table->string('domain')->nullable()->index();
                $table->string('document_type')->default('standard')->index();
                $table->string('status')->default('active')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('engineering_normative_sources')) {
            Schema::create('engineering_normative_sources', function (Blueprint $table) {
                $table->id();
                $table->foreignId('standard_id')->nullable()
                    ->constrained('engineering_normative_standards')->nullOnDelete();
                $table->unsignedBigInteger('duplicate_of_source_id')->nullable()->index();
                $table->string('relative_path')->unique();
                $table->string('filename');
                $table->string('extension', 16)->nullable();
                $table->string('sha256', 64)->nullable()->index();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->timestamp('source_modified_at')->nullable();
                $table->string('detected_code')->nullable()->index();
                $table->string('detected_title')->nullable();
                $table->string('document_type')->default('unclassified')->index();
                $table->string('extraction_status')->default('pending')->index();
                $table->string('text_path')->nullable();
                $table->boolean('is_exact_duplicate')->default(false)->index();
                $table->text('rejection_reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('indexed_at')->nullable();
                $table->timestamps();

                $table->foreign('duplicate_of_source_id')
                    ->references('id')->on('engineering_normative_sources')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('engineering_normative_editions')) {
            Schema::create('engineering_normative_editions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('standard_id')
                    ->constrained('engineering_normative_standards')->cascadeOnDelete();
                $table->foreignId('source_id')->nullable()
                    ->constrained('engineering_normative_sources')->nullOnDelete();
                $table->string('edition_label')->nullable();
                $table->unsignedSmallInteger('publication_year')->nullable()->index();
                $table->string('adoption_code')->nullable()->index();
                $table->boolean('is_current')->default(false)->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['standard_id', 'publication_year']);
            });
        }

        if (! Schema::hasTable('engineering_normative_sections_v2')) {
            Schema::create('engineering_normative_sections_v2', function (Blueprint $table) {
                $table->id();
                $table->foreignId('edition_id')
                    ->constrained('engineering_normative_editions')->cascadeOnDelete();
                $table->foreignId('source_id')->nullable()
                    ->constrained('engineering_normative_sources')->nullOnDelete();
                $table->string('section_code')->nullable()->index();
                $table->string('heading')->nullable();
                $table->longText('body');
                $table->string('body_hash', 64)->nullable()->index();
                $table->unsignedInteger('page_number')->nullable()->index();
                $table->unsignedInteger('sequence')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['edition_id', 'sequence']);
            });
        }

        if (! Schema::hasTable('engineering_normative_requirements')) {
            Schema::create('engineering_normative_requirements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('section_id')
                    ->constrained('engineering_normative_sections_v2')->cascadeOnDelete();
                $table->string('requirement_type')->default('other')->index();
                $table->string('subject')->nullable()->index();
                $table->text('requirement_text');
                $table->string('operator')->nullable();
                $table->decimal('min_value', 18, 6)->nullable();
                $table->decimal('max_value', 18, 6)->nullable();
                $table->decimal('nominal_value', 18, 6)->nullable();
                $table->decimal('tolerance_plus', 18, 6)->nullable();
                $table->decimal('tolerance_minus', 18, 6)->nullable();
                $table->string('unit', 32)->nullable();
                $table->text('source_context')->nullable();
                $table->decimal('extraction_confidence', 5, 2)->default(0);
                $table->json('keywords')->nullable();
                $table->timestamps();

                $table->index(['section_id', 'requirement_type']);
            });
        }

        if (! Schema::hasTable('engineering_project_normative_standards')) {
            Schema::create('engineering_project_normative_standards', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id')->index();
                $table->foreignId('standard_id')
                    ->constrained('engineering_normative_standards')->cascadeOnDelete();
                $table->string('applicability')->default('proposed')->index();
                $table->string('source')->default('agent');
                $table->unsignedBigInteger('validated_by')->nullable()->index();
                $table->timestamp('validated_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['project_id', 'standard_id'], 'eng_project_norm_std_unique');
            });
        }

        if (! Schema::hasTable('engineering_qa_normative_links')) {
            Schema::create('engineering_qa_normative_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inspection_id')->index();
                $table->foreignId('standard_id')
                    ->constrained('engineering_normative_standards')->cascadeOnDelete();
                $table->foreignId('edition_id')->nullable()
                    ->constrained('engineering_normative_editions')->nullOnDelete();
                $table->foreignId('section_id')->nullable()
                    ->constrained('engineering_normative_sections_v2')->nullOnDelete();
                $table->foreignId('requirement_id')->nullable()
                    ->constrained('engineering_normative_requirements')->nullOnDelete();
                $table->decimal('score', 6, 2)->default(0)->index();
                $table->text('reason')->nullable();
                $table->string('status')->default('proposed')->index();
                $table->unsignedBigInteger('validated_by')->nullable()->index();
                $table->timestamp('validated_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['inspection_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('engineering_qa_normative_links');
        Schema::dropIfExists('engineering_project_normative_standards');
        Schema::dropIfExists('engineering_normative_requirements');
        Schema::dropIfExists('engineering_normative_sections_v2');
        Schema::dropIfExists('engineering_normative_editions');
        Schema::dropIfExists('engineering_normative_sources');
        Schema::dropIfExists('engineering_normative_standards');
    }
};
