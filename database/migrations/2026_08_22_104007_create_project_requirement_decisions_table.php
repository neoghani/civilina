<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_requirement_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_standard_profile_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('normative_requirement_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('applicability', 40)
                ->default('pending');
            $table->text('rationale');
            $table->foreignId('validated_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('validated_at');
            $table->timestamps();

            $table->unique([
                'project_standard_profile_id',
                'normative_requirement_id',
            ]);

            $table->index([
                'project_standard_profile_id',
                'applicability',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_requirement_decisions');
    }
};
