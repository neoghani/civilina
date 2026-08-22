<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artifacts', function (Blueprint $table) {
            $table->foreign('current_revision_id')
                ->references('id')
                ->on('artifact_revisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('artifacts', function (Blueprint $table) {
            $table->dropForeign(
                ['current_revision_id']
            );
        });
    }
};