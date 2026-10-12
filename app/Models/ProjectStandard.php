<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStandard extends Model
{
    protected $fillable = [
        'project_standard_profile_id',
        'standard_edition_id',
        'scope_notes',
        'priority',
        'is_contractual',
        'validated_by_user_id',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_contractual' => 'boolean',
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

    public function edition(): BelongsTo
    {
        return $this->belongsTo(StandardEdition::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'validated_by_user_id'
        );
    }
}
