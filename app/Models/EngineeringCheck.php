<?php

namespace App\Models;

use App\Enums\EngineeringCheckOutcome;
use App\Enums\EngineeringCheckStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngineeringCheck extends Model
{
    protected $fillable = [
        'analysis_result_set_id',
        'technical_object_revision_id',
        'normative_requirement_id',
        'check_code',
        'check_type',
        'title',
        'status',
        'outcome',
        'criterion',
        'observed',
        'utilization_ratio',
        'calculation_trace',
        'recommendation',
        'created_by_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EngineeringCheckStatus::class,
            'outcome' => EngineeringCheckOutcome::class,
            'criterion' => 'array',
            'observed' => 'array',
            'utilization_ratio' => 'decimal:6',
            'calculation_trace' => 'array',
            'reviewed_at' => 'datetime',
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

    public function normativeRequirement(): BelongsTo
    {
        return $this->belongsTo(NormativeRequirement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by_user_id'
        );
    }

    public function findings(): HasMany
    {
        return $this->hasMany(EngineeringFinding::class);
    }
}
