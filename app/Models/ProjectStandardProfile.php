<?php

namespace App\Models;

use App\Enums\ProjectStandardProfileStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectStandardProfile extends Model
{
    protected $fillable = [
        'project_id',
        'revision_no',
        'name',
        'status',
        'change_summary',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'revision_no' => 'integer',
            'status' => ProjectStandardProfileStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function standards(): HasMany
    {
        return $this->hasMany(ProjectStandard::class);
    }

    public function requirementDecisions(): HasMany
    {
        return $this->hasMany(ProjectRequirementDecision::class);
    }
}
