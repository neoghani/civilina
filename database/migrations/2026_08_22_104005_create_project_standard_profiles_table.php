<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_standard_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->string('name');
            $table->string('status', 40)->default('draft');
            $table->text('change_summary')->nullable();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique([
                'project_id',
                'revision_no',
            ]);

            $table->index([
                'project_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_standard_profiles');
    }
};
