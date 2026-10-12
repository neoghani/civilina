<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('engineering_qa_normative_links')) {
            Schema::create('engineering_qa_normative_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inspection_id')->nullable()->index();
                $table->unsignedBigInteger('requirement_id')->nullable()->index();
                $table->unsignedBigInteger('standard_id')->nullable()->index();
                $table->unsignedBigInteger('edition_id')->nullable()->index();
                $table->unsignedBigInteger('section_id')->nullable()->index();
                $table->decimal('score', 6, 2)->default(0)->index();
                $table->text('reason')->nullable();
                $table->string('status')->default('proposed')->index();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('validated_by')->nullable()->index();
                $table->timestamp('validated_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();

                $table->index(['inspection_id', 'status']);
            });

            return;
        }

        $columns = [
            'inspection_id' => Schema::hasColumn('engineering_qa_normative_links', 'inspection_id'),
            'requirement_id' => Schema::hasColumn('engineering_qa_normative_links', 'requirement_id'),
            'standard_id' => Schema::hasColumn('engineering_qa_normative_links', 'standard_id'),
            'edition_id' => Schema::hasColumn('engineering_qa_normative_links', 'edition_id'),
            'section_id' => Schema::hasColumn('engineering_qa_normative_links', 'section_id'),
            'score' => Schema::hasColumn('engineering_qa_normative_links', 'score'),
            'reason' => Schema::hasColumn('engineering_qa_normative_links', 'reason'),
            'status' => Schema::hasColumn('engineering_qa_normative_links', 'status'),
            'metadata' => Schema::hasColumn('engineering_qa_normative_links', 'metadata'),
            'validated_by' => Schema::hasColumn('engineering_qa_normative_links', 'validated_by'),
            'validated_at' => Schema::hasColumn('engineering_qa_normative_links', 'validated_at'),
            'rejected_at' => Schema::hasColumn('engineering_qa_normative_links', 'rejected_at'),
            'created_at' => Schema::hasColumn('engineering_qa_normative_links', 'created_at'),
            'updated_at' => Schema::hasColumn('engineering_qa_normative_links', 'updated_at'),
        ];

        Schema::table('engineering_qa_normative_links', function (Blueprint $table) use ($columns) {
            if (! $columns['inspection_id']) {
                $table->unsignedBigInteger('inspection_id')->nullable()->index();
            }
            if (! $columns['requirement_id']) {
                $table->unsignedBigInteger('requirement_id')->nullable()->index();
            }
            if (! $columns['standard_id']) {
                $table->unsignedBigInteger('standard_id')->nullable()->index();
            }
            if (! $columns['edition_id']) {
                $table->unsignedBigInteger('edition_id')->nullable()->index();
            }
            if (! $columns['section_id']) {
                $table->unsignedBigInteger('section_id')->nullable()->index();
            }
            if (! $columns['score']) {
                $table->decimal('score', 6, 2)->default(0)->index();
            }
            if (! $columns['reason']) {
                $table->text('reason')->nullable();
            }
            if (! $columns['status']) {
                $table->string('status')->default('proposed')->index();
            }
            if (! $columns['metadata']) {
                $table->json('metadata')->nullable();
            }
            if (! $columns['validated_by']) {
                $table->unsignedBigInteger('validated_by')->nullable()->index();
            }
            if (! $columns['validated_at']) {
                $table->timestamp('validated_at')->nullable();
            }
            if (! $columns['rejected_at']) {
                $table->timestamp('rejected_at')->nullable();
            }
            if (! $columns['created_at']) {
                $table->timestamp('created_at')->nullable();
            }
            if (! $columns['updated_at']) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Correctif de compatibilité : rollback volontairement non destructif.
    }
};
