<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_object_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('technical_object_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->string('status', 40)->default('draft');
            $table->foreignId('material_id')
                ->nullable()
                ->constrained('materials')
                ->restrictOnDelete();
            $table->json('geometry');
            $table->json('properties')->nullable();
            $table->json('assumptions')->nullable();
            $table->json('provenance')->nullable();
            $table->text('change_summary')->nullable();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique([
                'technical_object_id',
                'revision_no',
            ]);

            $table->index([
                'technical_object_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_object_revisions');
    }
};
