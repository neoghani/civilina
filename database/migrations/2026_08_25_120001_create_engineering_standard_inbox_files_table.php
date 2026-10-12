<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('engineering_standard_inbox_files')) {
            return;
        }

        Schema::create('engineering_standard_inbox_files', function (Blueprint $table): void {
            $table->id();
            $table->string('relative_path', 700)->unique();
            $table->string('original_name');
            $table->string('extension', 16)->nullable();
            $table->char('sha256', 64)->index();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status', 24)->default('pending')->index();
            $table->string('standard_document_id', 64)->nullable()->index();
            $table->string('detected_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_scanned_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engineering_standard_inbox_files');
    }
};
