<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('engineering_normative_standards')) {
            return;
        }

        Schema::table('engineering_normative_standards', function (Blueprint $table) {
            if (! Schema::hasColumn('engineering_normative_standards', 'discipline')) {
                $table->string('discipline')->nullable()->index();
            }
            if (! Schema::hasColumn('engineering_normative_standards', 'subdomain')) {
                $table->string('subdomain')->nullable()->index();
            }
            if (! Schema::hasColumn('engineering_normative_standards', 'family_name')) {
                $table->string('family_name')->nullable()->index();
            }
            if (! Schema::hasColumn('engineering_normative_standards', 'source_collection')) {
                $table->string('source_collection')->nullable()->index();
            }
            if (! Schema::hasColumn('engineering_normative_standards', 'classification_status')) {
                $table->string('classification_status')->default('auto')->index();
            }
        });
    }

    public function down(): void
    {
        // Non destructif : les champs de classement sont conservés.
    }
};
