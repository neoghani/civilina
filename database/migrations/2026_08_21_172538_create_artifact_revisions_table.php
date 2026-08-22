<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifact_revisions', function (Blueprint $table) {
            $table->id();

            $table->ulid('public_id')->unique();

            $table->foreignId('artifact_id')
                ->constrained('artifacts')
                ->cascadeOnDelete();

            $table->unsignedInteger('revision_number');

            $table->string('revision_label', 50);

            $table->string('status', 32)
                ->default('draft')
                ->index();

            /*
             * Contenu métier structuré :
             * WBS, ITP, Risk Register,
             * RACI, Workflow, Dashboard...
             */
            $table->json('payload');

            /*
             * Sources utilisées pour produire
             * cette révision :
             * utilisateur, Civilina AI,
             * import, données projet, etc.
             */
            $table->json('provenance')->nullable();

            $table->text('change_summary')->nullable();

            $table->string('source', 50)
                ->default('civilina_ai')
                ->index();

            /*
             * Permet plus tard de vérifier
             * l'intégrité d'une révision.
             */
            $table->string('checksum', 64)
                ->nullable()
                ->index();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['artifact_id', 'revision_number'],
                'artifact_revision_number_unique'
            );

            $table->index(
                ['artifact_id', 'status'],
                'artifact_revision_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifact_revisions');
    }
};