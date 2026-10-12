<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standard_editions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('standard_document_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('edition_code', 100);
            $table->date('publication_date')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('withdrawn_at')->nullable();
            $table->string('status', 40)->default('draft');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique([
                'standard_document_id',
                'edition_code',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standard_editions');
    }
};
