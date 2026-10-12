<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('civilina_normative_registry_requirements')) {
            Schema::create('civilina_normative_registry_requirements', function (Blueprint $table) {
                $table->id();

                // Provenance vers la zone de staging Deep Extraction.
                $table->unsignedBigInteger('source_candidate_id')->unique();
                $table->unsignedBigInteger('source_document_id')->index();
                $table->unsignedBigInteger('deep_document_id')->index();
                $table->unsignedBigInteger('source_section_id')->nullable()->index();

                // Identité normative publiée.
                $table->string('reference_code')->index();
                $table->string('edition_key')->nullable()->index();
                $table->string('section_code')->nullable()->index();
                $table->string('heading')->nullable();

                // Contenu validé humainement.
                $table->string('requirement_type')->index(); // REQUIREMENT | TOLERANCE
                $table->longText('requirement_text');
                $table->string('marker')->nullable();
                $table->string('numeric_value')->nullable();
                $table->string('unit')->nullable();
                $table->string('operator')->nullable();

                // Preuve de validation.
                $table->decimal('extraction_confidence', 5, 2)->default(0);
                $table->text('human_validation_note')->nullable();
                $table->timestamp('human_validated_at')->nullable();

                // Publication contrôlée et traçabilité.
                $table->string('publication_state')->default('ACTIVE')->index();
                $table->unsignedInteger('revision')->default(1);
                $table->string('source_hash', 64)->index();
                $table->string('provenance_hash', 64)->unique();
                $table->unsignedBigInteger('last_publication_run_id')->nullable()->index();
                $table->string('publication_actor')->default('SYSTEM_CONTROLLED');
                $table->timestamp('published_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->text('revocation_reason')->nullable();

                $table->timestamps();
            });
        }

        if (! Schema::hasTable('civilina_normative_publication_runs')) {
            Schema::create('civilina_normative_publication_runs', function (Blueprint $table) {
                $table->id();
                $table->string('status')->default('RUNNING')->index();
                $table->boolean('dry_run')->default(false);
                $table->unsignedInteger('requested_limit')->nullable();

                $table->unsignedInteger('eligible')->default(0);
                $table->unsignedInteger('selected')->default(0);
                $table->unsignedInteger('published')->default(0);
                $table->unsignedInteger('republished')->default(0);
                $table->unsignedInteger('skipped')->default(0);
                $table->unsignedInteger('refused')->default(0);
                $table->unsignedInteger('errors')->default(0);

                $table->text('report_path')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('civilina_normative_publication_events')) {
            Schema::create('civilina_normative_publication_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('registry_requirement_id')->nullable()->index();
                $table->unsignedBigInteger('source_candidate_id')->index();
                $table->unsignedBigInteger('publication_run_id')->nullable()->index();

                $table->string('event_type')->index(); // PUBLISH | REPUBLISH | REVOKE
                $table->string('source_hash', 64)->nullable();
                $table->string('provenance_hash', 64)->nullable();
                $table->text('reason')->nullable();
                $table->string('actor')->default('SYSTEM_CONTROLLED');

                $table->timestamp('event_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Non destructif volontairement : le registre publié est une preuve d'audit.
    }
};
