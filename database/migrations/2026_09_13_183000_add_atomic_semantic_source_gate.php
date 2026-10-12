<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('civilina_normative_atomic_statements', function (Blueprint $table) {
            if (! Schema::hasColumn('civilina_normative_atomic_statements', 'semantic_version')) {
                $table->string('semantic_version')->nullable()->index();
            }
            if (! Schema::hasColumn('civilina_normative_atomic_statements', 'source_authority')) {
                $table->string('source_authority')->default('UNVERIFIED')->index();
            }
            if (! Schema::hasColumn('civilina_normative_atomic_statements', 'source_authority_reason')) {
                $table->text('source_authority_reason')->nullable();
            }
            if (! Schema::hasColumn('civilina_normative_atomic_statements', 'publication_eligible')) {
                $table->boolean('publication_eligible')->default(false)->index();
            }
            if (! Schema::hasColumn('civilina_normative_atomic_statements', 'eligibility_reason')) {
                $table->text('eligibility_reason')->nullable();
            }
        });

        if (! Schema::hasTable('civilina_normative_atomic_source_reviews')) {
            Schema::create('civilina_normative_atomic_source_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('deep_document_id')->unique();
                $table->unsignedBigInteger('document_id')->index();
                $table->string('reference_code')->nullable()->index();
                $table->string('edition_key')->nullable()->index();

                $table->string('detected_authority')->default('UNVERIFIED')->index();
                $table->text('detected_reason')->nullable();

                // UNREVIEWED | PRIMARY_VALIDATED | SECONDARY_REJECTED
                $table->string('review_status')->default('UNREVIEWED')->index();
                $table->text('review_note')->nullable();
                $table->timestamp('reviewed_at')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Non destructif.
    }
};
