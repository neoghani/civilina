<?php

namespace App\Models;

use App\Enums\AnalysisResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalysisResultSet extends Model
{
    protected $fillable = [
        'analysis_run_id',
        'status',
        'solver_version',
        'source_reference',
        'result_hash',
        'summary',
        'imported_by_user_id',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AnalysisResultStatus::class,
            'summary' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(AnalysisResult::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'imported_by_user_id'
        );
    }

    public function checks(): HasMany
    {
        return $this->hasMany(EngineeringCheck::class);
    }
}
