<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('work_package_id')
                ->constrained('work_packages')
                ->restrictOnDelete();
            $table->foreignId('zone_id')
                ->nullable()
                ->constrained('project_zones')
                ->restrictOnDelete();
            $table->foreignId('ouvrage_id')
                ->nullable()
                ->constrained('project_ouvrages')
                ->restrictOnDelete();
            $table->string('code', 150);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('activity_type', 100)->nullable();
            $table->string('status', 40)->default('planned');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'code']);
            $table->index([
                'project_id',
                'work_package_id',
                'zone_id',
                'ouvrage_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
