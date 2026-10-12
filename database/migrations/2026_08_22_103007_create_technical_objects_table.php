<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_objects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('technical_objects')
                ->restrictOnDelete();
            $table->foreignId('wbs_node_id')
                ->nullable()
                ->constrained('wbs_nodes')
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
            $table->string('object_type', 100);
            $table->string('discipline', 100);
            $table->timestamps();

            $table->unique(['project_id', 'code']);
            $table->index([
                'project_id',
                'wbs_node_id',
                'zone_id',
                'ouvrage_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_objects');
    }
};
