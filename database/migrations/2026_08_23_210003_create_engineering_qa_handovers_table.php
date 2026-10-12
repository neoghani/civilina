<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('engineering_qa_handovers')) return;

        Schema::create('engineering_qa_handovers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('organization_id', 64)->index();
            $table->string('project_id', 64)->index();
            $table->string('code', 120);
            $table->string('from_work_type', 80);
            $table->string('to_work_type', 80);
            $table->string('scope_type', 40)->default('project');
            $table->string('scope_ref', 120)->nullable();
            $table->string('scope_label');
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('client_authorized_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('client_reference')->nullable();
            $table->string('from_responsible')->nullable();
            $table->string('to_responsible')->nullable();
            $table->string('client_representative')->nullable();
            $table->json('criteria');
            $table->text('notes')->nullable();
            $table->string('created_by', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'project_id', 'code'],
                'engineering_qa_handovers_scope_code_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engineering_qa_handovers');
    }
};
