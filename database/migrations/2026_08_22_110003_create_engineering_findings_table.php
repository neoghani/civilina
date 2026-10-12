<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engineering_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('engineering_check_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('severity', 40);
            $table->string('code', 120);
            $table->string('title');
            $table->text('description');
            $table->text('recommended_action')->nullable();
            $table->string('status', 40)->default('open');
            $table->timestamps();

            $table->unique([
                'engineering_check_id',
                'code',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engineering_findings');
    }
};
