<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analysis_result_set_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('technical_object_revision_id')
                ->constrained('technical_object_revisions')
                ->restrictOnDelete();
            $table->string('result_type', 100);
            $table->string('component', 80)->nullable();
            $table->string('load_case_code', 100)->nullable();
            $table->string('combination_code', 100)->nullable();
            $table->json('location')->nullable();
            $table->decimal('value', 24, 8);
            $table->string('unit', 40)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index([
                'analysis_result_set_id',
                'technical_object_revision_id',
                'result_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_results');
    }
};
