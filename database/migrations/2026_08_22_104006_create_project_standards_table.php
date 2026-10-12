<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_standards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_standard_profile_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('standard_edition_id')
                ->constrained()
                ->restrictOnDelete();
            $table->text('scope_notes')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_contractual')->default(false);
            $table->foreignId('validated_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('validated_at');
            $table->timestamps();

            $table->unique([
                'project_standard_profile_id',
                'standard_edition_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_standards');
    }
};
