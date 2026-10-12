<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('engineering_qa_normative_links')) {
            return;
        }

        Schema::create('engineering_qa_normative_links', function (Blueprint $table): void {
            $table->id();
            $table->string('organization_id', 64)->index();
            $table->string('project_id', 64)->index();
            $table->foreignId('engineering_qa_inspection_id')
                ->constrained('engineering_qa_inspections')
                ->cascadeOnDelete();

            // Pas de FK volontaire vers la bibliothèque normative :
            // Civilina peut utiliser des clés numériques ou UUID selon le noyau installé.
            $table->string('standard_section_id', 64)->nullable()->index();
            $table->string('standard_document_id', 64)->nullable()->index();
            $table->string('standard_edition_id', 64)->nullable()->index();

            $table->string('source_label')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('clause_code', 120)->nullable();
            $table->text('excerpt')->nullable();

            $table->decimal('confidence_score', 5, 2)->default(0);
            $table->text('match_reason')->nullable();
            $table->boolean('project_profile_match')->default(false)->index();

            $table->string('status', 24)->default('suggested')->index();
            $table->string('suggested_by', 80)->default('civilina_normative_agent_v1');
            $table->string('validated_by', 64)->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(
                ['organization_id', 'project_id', 'engineering_qa_inspection_id'],
                'qa_normative_links_scope_inspection_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engineering_qa_normative_links');
    }
};
