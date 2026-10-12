<?php

namespace App\Models;

use App\Enums\RequirementValidationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NormativeRequirement extends Model
{
    protected $fillable = [
        'standard_section_id',
        'requirement_code',
        'requirement_type',
        'summary',
        'interpretation',
        'source_excerpt',
        'source_locator',
        'page_number',
        'validation_status',
        'validated_by_user_id',
        'validated_at',
        'validation_note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'validation_status' => RequirementValidationStatus::class,
            'validated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(StandardSection::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'validated_by_user_id'
        );
    }

    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(
            RequirementRelation::class,
            'source_requirement_id'
        );
    }

    public function incomingRelations(): HasMany
    {
        return $this->hasMany(
            RequirementRelation::class,
            'target_requirement_id'
        );
    }

    public function applicabilityDecisions(): HasMany
    {
        return $this->hasMany(ProjectRequirementDecision::class);
    }
}
