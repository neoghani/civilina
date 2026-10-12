<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engineering_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analysis_result_set_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('technical_object_revision_id')
                ->constrained('technical_object_revisions')
                ->restrictOnDelete();
            $table->foreignId('normative_requirement_id')
                ->nullable()
                ->constrained('normative_requirements')
                ->restrictOnDelete();
            $table->string('check_code', 120);
            $table->string('check_type', 100);
            $table->string('title');
            $table->string('status', 40)->default('draft');
            $table->string('outcome', 40)->default('not_evaluated');
            $table->json('criterion')->nullable();
            $table->json('observed')->nullable();
            $table->decimal('utilization_ratio', 12, 6)->nullable();
            $table->json('calculation_trace')->nullable();
            $table->text('recommendation')->nullable();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique([
                'analysis_result_set_id',
                'check_code',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engineering_checks');
    }
};
