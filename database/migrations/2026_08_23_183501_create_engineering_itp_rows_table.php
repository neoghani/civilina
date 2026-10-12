<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('engineering_itp_rows')) return;

        Schema::create('engineering_itp_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('engineering_itp_id')->constrained('engineering_itps')->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(10);
            $table->string('code', 80);
            $table->text('inspection_test');
            $table->text('method')->nullable();
            $table->string('frequency', 500)->nullable();
            $table->text('acceptance_criteria');
            $table->string('checkpoint_type', 32);
            $table->string('responsibility_execute');
            $table->string('responsibility_inspect');
            $table->string('responsibility_approve')->nullable();
            $table->string('responsibility_accept')->nullable();
            $table->text('record_required');
            $table->text('source_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['engineering_itp_id', 'code'], 'engineering_itp_rows_itp_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engineering_itp_rows');
    }
};
