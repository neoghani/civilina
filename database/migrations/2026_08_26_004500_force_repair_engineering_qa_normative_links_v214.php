<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('engineering_qa_normative_links')) {
            Schema::create('engineering_qa_normative_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inspection_id')->nullable()->index();
                $table->unsignedBigInteger('requirement_id')->nullable()->index();
                $table->unsignedBigInteger('standard_id')->nullable()->index();
                $table->unsignedBigInteger('edition_id')->nullable()->index();
                $table->unsignedBigInteger('section_id')->nullable()->index();
                $table->decimal('score', 6, 2)->default(0)->index();
                $table->text('reason')->nullable();
                $table->string('status')->default('proposed')->index();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('validated_by')->nullable()->index();
                $table->timestamp('validated_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();
            });

            return;
        }

        $required = [
            'inspection_id' => fn (Blueprint $table) => $table->unsignedBigInteger('inspection_id')->nullable()->index(),
            'requirement_id' => fn (Blueprint $table) => $table->unsignedBigInteger('requirement_id')->nullable()->index(),
            'standard_id' => fn (Blueprint $table) => $table->unsignedBigInteger('standard_id')->nullable()->index(),
            'edition_id' => fn (Blueprint $table) => $table->unsignedBigInteger('edition_id')->nullable()->index(),
            'section_id' => fn (Blueprint $table) => $table->unsignedBigInteger('section_id')->nullable()->index(),
            'score' => fn (Blueprint $table) => $table->decimal('score', 6, 2)->default(0)->index(),
            'reason' => fn (Blueprint $table) => $table->text('reason')->nullable(),
            'status' => fn (Blueprint $table) => $table->string('status')->default('proposed')->index(),
            'metadata' => fn (Blueprint $table) => $table->json('metadata')->nullable(),
            'validated_by' => fn (Blueprint $table) => $table->unsignedBigInteger('validated_by')->nullable()->index(),
            'validated_at' => fn (Blueprint $table) => $table->timestamp('validated_at')->nullable(),
            'rejected_at' => fn (Blueprint $table) => $table->timestamp('rejected_at')->nullable(),
            'created_at' => fn (Blueprint $table) => $table->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $table) => $table->timestamp('updated_at')->nullable(),
        ];

        foreach ($required as $column => $adder) {
            if (! Schema::hasColumn('engineering_qa_normative_links', $column)) {
                Schema::table('engineering_qa_normative_links', function (Blueprint $table) use ($adder) {
                    $adder($table);
                });
            }
        }
    }

    public function down(): void
    {
        // Correctif de compatibilité non destructif.
    }
};
