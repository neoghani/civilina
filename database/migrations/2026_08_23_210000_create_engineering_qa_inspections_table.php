<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('engineering_qa_inspections')) return;

        Schema::create('engineering_qa_inspections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('organization_id', 64)->index();
            $table->string('project_id', 64)->index();
            $table->foreignId('engineering_itp_id')->nullable()->constrained('engineering_itps')->nullOnDelete();
            $table->foreignId('engineering_itp_row_id')->nullable()->constrained('engineering_itp_rows')->nullOnDelete();
            $table->string('template_key', 120)->index();
            $table->string('work_type', 80)->index();
            $table->string('inspection_code', 120);
            $table->string('title');
            $table->string('record_type', 60)->index();
            $table->string('register_type', 80)->nullable()->index();
            $table->string('checkpoint_type', 32)->nullable();
            $table->string('scope_type', 40)->default('project');
            $table->string('scope_ref', 120)->nullable();
            $table->string('scope_label');
            $table->string('status', 32)->default('planned')->index();
            $table->string('authorization_status', 32)->default('none')->index();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('planned_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('client_authorized_at')->nullable();
            $table->string('client_reference')->nullable();
            $table->string('inspector')->nullable();
            $table->string('client_representative')->nullable();
            $table->text('acceptance_criteria')->nullable();
            $table->text('requirement_source')->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'project_id', 'inspection_code'],
                'engineering_qa_inspections_scope_code_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engineering_qa_inspections');
    }
};
