<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'artifacts',
            function (Blueprint $table): void {
                $table
                    ->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->restrictOnDelete();

                $table->index([
                    'organization_id',
                    'type',
                    'status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'artifacts',
            function (Blueprint $table): void {
                $table->dropIndex([
                    'organization_id',
                    'type',
                    'status',
                ]);

                $table->dropConstrainedForeignId(
                    'organization_id'
                );
            }
        );
    }
};
