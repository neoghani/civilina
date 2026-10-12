<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engineering_verification_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('project_standard_profile_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('normative_requirement_id')
                ->constrained('normative_requirements')
                ->restrictOnDelete();
            $table->string('code', 120);
            $table->string('title');
            $table->string('result_type', 100);
            $table->string('component', 80)->nullable();
            $table->string('operator', 40);
            $table->decimal('limit_value', 24, 8);
            $table->string('limit_unit', 40);
            $table->string('failure_severity', 40)->default('high');
            $table->string('status', 40)->default('draft');
            $table->json('rule_payload')->nullable();
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

            $table->unique(['project_id', 'code']);
            $table->index([
                'project_id',
                'status',
                'result_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engineering_verification_rules');
    }
};
