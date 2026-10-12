<?php

namespace App\Models;

use App\Enums\RequirementApplicability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRequirementDecision extends Model
{
    protected $fillable = [
        'project_standard_profile_id',
        'normative_requirement_id',
        'applicability',
        'rationale',
        'validated_by_user_id',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'applicability' => RequirementApplicability::class,
            'validated_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(
            ProjectStandardProfile::class,
            'project_standard_profile_id'
        );
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(NormativeRequirement::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'validated_by_user_id'
        );
    }
}
