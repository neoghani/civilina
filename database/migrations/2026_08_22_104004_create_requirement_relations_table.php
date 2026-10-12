<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirement_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_requirement_id')
                ->constrained('normative_requirements')
                ->cascadeOnDelete();
            $table->foreignId('target_requirement_id')
                ->constrained('normative_requirements')
                ->cascadeOnDelete();
            $table->string('relation_type', 80);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique([
                'source_requirement_id',
                'target_requirement_id',
                'relation_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_relations');
    }
};
