<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisResult extends Model
{
    protected $fillable = [
        'analysis_result_set_id',
        'technical_object_revision_id',
        'result_type',
        'component',
        'load_case_code',
        'combination_code',
        'location',
        'value',
        'unit',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'location' => 'array',
            'value' => 'decimal:8',
            'raw_payload' => 'array',
        ];
    }

    public function resultSet(): BelongsTo
    {
        return $this->belongsTo(
            AnalysisResultSet::class,
            'analysis_result_set_id'
        );
    }

    public function technicalObjectRevision(): BelongsTo
    {
        return $this->belongsTo(
            TechnicalObjectRevision::class,
            'technical_object_revision_id'
        );
    }
}
