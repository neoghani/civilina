<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('normative_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('standard_section_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('requirement_code', 180);
            $table->string('requirement_type', 80);
            $table->text('summary');
            $table->text('interpretation')->nullable();
            $table->text('source_excerpt')->nullable();
            $table->string('source_locator')->nullable();
            $table->unsignedInteger('page_number')->nullable();
            $table->string('validation_status', 40)
                ->default('candidate');
            $table->foreignId('validated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique([
                'standard_section_id',
                'requirement_code',
            ]);

            $table->index([
                'standard_section_id',
                'requirement_type',
                'validation_status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('normative_requirements');
    }
};
