<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('engineering_itps')) return;

        Schema::create('engineering_itps', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('organization_id', 64)->index();
            $table->string('project_id', 64)->index();
            $table->string('code', 80);
            $table->string('title');
            $table->string('revision', 40)->default('DRAFT-01');
            $table->string('status', 32)->default('draft')->index();
            $table->string('scope_type', 40)->default('project');
            $table->string('scope_ref', 120)->nullable();
            $table->string('scope_label');
            $table->json('reference_documents');
            $table->text('notes')->nullable();
            $table->string('created_by', 64)->nullable();
            $table->string('reviewed_by', 64)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('approved_by', 64)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'project_id', 'code', 'revision'],
                'engineering_itps_scope_code_revision_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engineering_itps');
    }
};
