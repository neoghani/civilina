<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standard_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('code', 150);
            $table->string('title');
            $table->string('publisher')->nullable();
            $table->string('jurisdiction', 100)->nullable();
            $table->string('discipline', 100)->nullable();
            $table->string('document_type', 100)->nullable();
            $table->string('language', 20)->nullable();
            $table->string('source_filename')->nullable();
            $table->string('source_checksum', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index([
                'organization_id',
                'discipline',
                'jurisdiction',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standard_documents');
    }
};
