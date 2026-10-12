<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_result_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analysis_run_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('status', 40)->default('imported');
            $table->string('solver_version')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('result_hash', 64);
            $table->json('summary')->nullable();
            $table->foreignId('imported_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->unique('analysis_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_result_sets');
    }
};
