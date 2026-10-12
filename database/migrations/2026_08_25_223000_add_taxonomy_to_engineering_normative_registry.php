<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('engineering_normative_sources')) {
            Schema::table('engineering_normative_sources', function (Blueprint $table) {
                if (! Schema::hasColumn('engineering_normative_sources', 'category')) {
                    $table->string('category')->nullable()->index()->after('document_type');
                }
                if (! Schema::hasColumn('engineering_normative_sources', 'subcategory')) {
                    $table->string('subcategory')->nullable()->index()->after('category');
                }
                if (! Schema::hasColumn('engineering_normative_sources', 'domain')) {
                    $table->string('domain')->nullable()->index()->after('subcategory');
                }
            });
        }

        if (Schema::hasTable('engineering_normative_standards')) {
            Schema::table('engineering_normative_standards', function (Blueprint $table) {
                if (! Schema::hasColumn('engineering_normative_standards', 'category')) {
                    $table->string('category')->nullable()->index()->after('domain');
                }
                if (! Schema::hasColumn('engineering_normative_standards', 'subcategory')) {
                    $table->string('subcategory')->nullable()->index()->after('category');
                }
            });
        }
    }

    public function down(): void
    {
        // Migration volontairement non destructive.
    }
};
