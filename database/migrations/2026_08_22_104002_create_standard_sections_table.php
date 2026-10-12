<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standard_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('standard_edition_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('standard_sections')
                ->restrictOnDelete();
            $table->string('clause_reference', 150);
            $table->string('title');
            $table->unsignedInteger('page_start')->nullable();
            $table->unsignedInteger('page_end')->nullable();
            $table->string('source_locator')->nullable();
            $table->string('content_hash', 128)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique([
                'standard_edition_id',
                'clause_reference',
            ]);

            $table->index([
                'standard_edition_id',
                'parent_id',
                'sort_order',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standard_sections');
    }
};
