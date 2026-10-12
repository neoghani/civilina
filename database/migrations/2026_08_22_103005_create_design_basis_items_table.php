<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_basis_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('design_basis_revision_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('category', 60);
            $table->string('key', 150);
            $table->string('label');
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 20, 6)->nullable();
            $table->json('value_json')->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('source_type', 80)->nullable();
            $table->string('source_reference')->nullable();
            $table->boolean('is_assumption')->default(false);
            $table->timestamps();

            $table->unique([
                'design_basis_revision_id',
                'category',
                'key',
            ]);

            $table->index([
                'design_basis_revision_id',
                'category',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_basis_items');
    }
};
