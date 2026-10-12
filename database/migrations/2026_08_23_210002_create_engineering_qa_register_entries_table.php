<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('engineering_qa_register_entries')) return;

        Schema::create('engineering_qa_register_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('engineering_qa_inspection_id')->nullable()->constrained('engineering_qa_inspections')->cascadeOnDelete();
            $table->string('organization_id', 64)->index();
            $table->string('project_id', 64)->index();
            $table->string('work_type', 80)->index();
            $table->string('register_type', 80)->index();
            $table->string('reference');
            $table->date('entry_date')->index();
            $table->string('scope_label');
            $table->json('payload');
            $table->string('quality_status', 32)->default('pending')->index();
            $table->text('source_reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engineering_qa_register_entries');
    }
};
