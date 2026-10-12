<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technical_objects', function (Blueprint $table): void {
            $table->foreignId('work_package_id')
                ->nullable()
                ->constrained('work_packages')
                ->restrictOnDelete();

            $table->foreignId('activity_id')
                ->nullable()
                ->constrained('activities')
                ->restrictOnDelete();

            $table->index([
                'project_id',
                'work_package_id',
                'activity_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('technical_objects', function (Blueprint $table): void {
            $table->dropForeign([
                'activity_id',
            ]);
            $table->dropForeign([
                'work_package_id',
            ]);
            $table->dropIndex([
                'project_id',
                'work_package_id',
                'activity_id',
            ]);
            $table->dropColumn([
                'activity_id',
                'work_package_id',
            ]);
        });
    }
};
