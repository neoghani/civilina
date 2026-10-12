<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('engineering_qa_evidences')) return;

        Schema::create('engineering_qa_evidences', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('engineering_qa_inspection_id')->constrained('engineering_qa_inspections')->cascadeOnDelete();
            $table->string('organization_id', 64)->index();
            $table->string('project_id', 64)->index();
            $table->string('reference');
            $table->string('document_type', 120);
            $table->date('issued_at');
            $table->string('revision', 40)->nullable();
            $table->string('original_name');
            $table->string('disk', 40)->default('local');
            $table->string('path', 1000);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64)->index();
            $table->string('status', 32)->default('draft')->index();
            $table->string('uploaded_by', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engineering_qa_evidences');
    }
};
