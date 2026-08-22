<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifacts', function (Blueprint $table) {
            $table->id();

            $table->ulid('public_id')->unique();

            $table->string('type', 50)->index();

            $table->string('title');

            $table->string('status', 32)
                ->default('draft')
                ->index();

            /*
             * FK ajoutée après la création
             * de artifact_revisions pour éviter
             * une dépendance circulaire.
             */
            $table->unsignedBigInteger('current_revision_id')
                ->nullable()
                ->index();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('owner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * Prépare les futurs rattachements
             * Project / Organization sans les
             * inventer dès maintenant.
             */
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['type', 'status'],
                'artifacts_type_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifacts');
    }
};