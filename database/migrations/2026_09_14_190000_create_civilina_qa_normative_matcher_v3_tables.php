<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('civilina_qa_normative_match_runs_v3')) {
            Schema::create('civilina_qa_normative_match_runs_v3', function (Blueprint $table) {
                $table->id();
                $table->string('status')->default('RUNNING')->index();
                $table->boolean('pilot')->default(false)->index();
                $table->unsignedInteger('requested_limit')->nullable();
                $table->unsignedInteger('inspection_count')->default(0);
                $table->unsignedInteger('eligible_registry_count')->default(0);
                $table->unsignedInteger('match_count')->default(0);
                $table->unsignedInteger('proposed_count')->default(0);
                $table->unsignedInteger('no_match_count')->default(0);
                $table->unsignedInteger('legacy_seen')->default(0);
                $table->unsignedInteger('legacy_preserved')->default(0);
                $table->text('report_path')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('civilina_qa_normative_matches_v3')) {
            Schema::create('civilina_qa_normative_matches_v3', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('matching_run_id')->index();
                $table->unsignedBigInteger('inspection_id')->index();
                $table->unsignedBigInteger('registry_requirement_id')->nullable()->index();
                $table->string('result_status')->index(); // MATCH | PROPOSED | NO_MATCH
                $table->decimal('score', 6, 2)->default(0);
                $table->string('business_family')->nullable()->index();
                $table->text('justification');
                $table->json('evidence');
                $table->json('provenance');
                $table->string('matcher_version')->default('CIVILINA_QA_NORMATIVE_V3')->index();
                $table->string('review_status')->default('NOT_REQUIRED')->index();
                $table->text('review_note')->nullable();
                $table->string('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(
                    ['matching_run_id', 'inspection_id', 'result_status'],
                    'civ_qa_match_v3_run_inspection_idx'
                );
            });
        }

        if (! Schema::hasTable('civilina_qa_normative_match_review_events_v3')) {
            Schema::create('civilina_qa_normative_match_review_events_v3', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('match_id')->index();
                $table->string('previous_status')->index();
                $table->string('decision')->index();
                $table->text('note');
                $table->string('actor');
                $table->json('match_snapshot');
                $table->timestamp('decided_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Non-destructive: V3 results and human review evidence are audit records.
    }
};
