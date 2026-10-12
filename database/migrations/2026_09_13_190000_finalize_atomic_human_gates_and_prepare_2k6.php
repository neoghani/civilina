<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('civilina_normative_atomic_statements', function (Blueprint $table) {
            if (! Schema::hasColumn('civilina_normative_atomic_statements', 'review_note')) {
                $table->text('review_note')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_atomic_statements', 'reviewed_by_user_id')) {
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_atomic_statements', 'reviewed_by_name')) {
                $table->string('reviewed_by_name')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_atomic_statements', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_atomic_statements', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_atomic_statements', 'revocation_reason')) {
                $table->text('revocation_reason')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_atomic_statements', 'provenance_hash')) {
                $table->string('provenance_hash', 64)->nullable()->index('civ_atomic_provenance_idx');
            }
            if (! Schema::hasColumn('civilina_normative_atomic_statements', 'extraction_state')) {
                $table->string('extraction_state')->default('ACTIVE')->index();
            }
            if (! Schema::hasColumn('civilina_normative_atomic_statements', 'superseded_at')) {
                $table->timestamp('superseded_at')->nullable()->index();
            }
        });

        Schema::table('civilina_normative_atomic_source_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('civilina_normative_atomic_source_reviews', 'reviewed_by_user_id')) {
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_atomic_source_reviews', 'reviewed_by_name')) {
                $table->string('reviewed_by_name')->nullable();
            }
        });

        if (! Schema::hasTable('civilina_normative_source_review_events')) {
            Schema::create('civilina_normative_source_review_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('source_review_id')->index();
                $table->unsignedBigInteger('deep_document_id')->index();
                $table->string('previous_status')->nullable()->index();
                $table->string('decision')->index();
                $table->text('note');
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
                $table->string('actor_name')->nullable();
                $table->json('source_snapshot')->nullable();
                $table->timestamp('decided_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('civilina_normative_atomic_review_events')) {
            Schema::create('civilina_normative_atomic_review_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('atomic_statement_id')->index();
                $table->string('previous_status')->nullable()->index();
                $table->string('decision')->index();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
                $table->string('actor_name')->nullable();
                $table->string('statement_hash', 64);
                $table->string('provenance_hash', 64)->nullable();
                $table->timestamp('decided_at');
                $table->timestamps();
            });
        }

        $this->extendPublishedRegistry();
        $this->createClassificationPreparation();
    }

    private function extendPublishedRegistry(): void
    {
        Schema::table('civilina_normative_registry_requirements', function (Blueprint $table) {
            if (! Schema::hasColumn('civilina_normative_registry_requirements', 'source_atomic_statement_id')) {
                $table->unsignedBigInteger('source_atomic_statement_id')
                    ->nullable()
                    ->unique('civ_norm_reg_atomic_unique');
            }
        });

        if (Schema::hasColumn('civilina_normative_registry_requirements', 'source_candidate_id')) {
            Schema::table('civilina_normative_registry_requirements', function (Blueprint $table) {
                $table->unsignedBigInteger('source_candidate_id')->nullable()->change();
            });
        }

        Schema::table('civilina_normative_publication_events', function (Blueprint $table) {
            if (! Schema::hasColumn('civilina_normative_publication_events', 'source_atomic_statement_id')) {
                $table->unsignedBigInteger('source_atomic_statement_id')
                    ->nullable()
                    ->index('civ_norm_event_atomic_idx');
            }
        });

        if (Schema::hasColumn('civilina_normative_publication_events', 'source_candidate_id')) {
            Schema::table('civilina_normative_publication_events', function (Blueprint $table) {
                $table->unsignedBigInteger('source_candidate_id')->nullable()->change();
            });
        }

        Schema::table('civilina_normative_publication_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('civilina_normative_publication_runs', 'run_mode')) {
                $table->string('run_mode')->default('CONTROLLED')->index();
            }
        });

        if (! Schema::hasTable('civilina_normative_registry_revisions')) {
            Schema::create('civilina_normative_registry_revisions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('registry_requirement_id')->index();
                $table->unsignedBigInteger('source_atomic_statement_id')->nullable()->index();
                $table->unsignedInteger('revision');
                $table->string('event_type')->index();
                $table->string('publication_state')->index();
                $table->string('source_hash', 64);
                $table->string('provenance_hash', 64);
                $table->json('snapshot');
                $table->string('actor')->nullable();
                $table->text('reason')->nullable();
                $table->timestamp('recorded_at');
                $table->timestamps();
                $table->unique(
                    ['registry_requirement_id', 'revision', 'event_type'],
                    'civ_norm_registry_revision_unique'
                );
            });
        }
    }

    private function createClassificationPreparation(): void
    {
        if (Schema::hasTable('civilina_normative_classifications')) {
            return;
        }

        Schema::create('civilina_normative_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('target_type')->index(); // DOCUMENT | STATEMENT
            $table->unsignedBigInteger('target_id');
            $table->unsignedBigInteger('document_id')->nullable()->index();
            $table->unsignedBigInteger('deep_document_id')->nullable()->index();
            $table->unsignedBigInteger('atomic_statement_id')->nullable()->index();
            $table->string('family')->nullable()->index();
            $table->string('suggested_family')->nullable()->index();
            $table->string('classification_status')->default('UNCLASSIFIED')->index();
            $table->json('evidence')->nullable();
            $table->json('source_fields')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->index();
            $table->string('reviewed_by_name')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['target_type', 'target_id'],
                'civ_norm_classification_target_unique'
            );
        });
    }

    public function down(): void
    {
        // Non-destructive: review evidence and audit history are retained.
    }
};
