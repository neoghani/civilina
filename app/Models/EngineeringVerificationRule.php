<?php

namespace App\Models;

use App\Enums\EngineeringFindingSeverity;
use App\Enums\EngineeringVerificationOperator;
use App\Enums\EngineeringVerificationRuleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineeringVerificationRule extends Model
{
    protected $fillable = [
        'project_id',
        'project_standard_profile_id',
        'normative_requirement_id',
        'code',
        'title',
        'result_type',
        'component',
        'operator',
        'limit_value',
        'limit_unit',
        'failure_severity',
        'status',
        'rule_payload',
        'created_by_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'operator' => EngineeringVerificationOperator::class,
            'limit_value' => 'decimal:8',
            'failure_severity' => EngineeringFindingSeverity::class,
            'status' => EngineeringVerificationRuleStatus::class,
            'rule_payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectStandardProfile(): BelongsTo
    {
        return $this->belongsTo(ProjectStandardProfile::class);
    }

    public function normativeRequirement(): BelongsTo
    {
        return $this->belongsTo(NormativeRequirement::class);
    }
}
