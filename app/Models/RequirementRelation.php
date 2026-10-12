<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequirementRelation extends Model
{
    protected $fillable = [
        'source_requirement_id',
        'target_requirement_id',
        'relation_type',
        'notes',
    ];

    public function sourceRequirement(): BelongsTo
    {
        return $this->belongsTo(
            NormativeRequirement::class,
            'source_requirement_id'
        );
    }

    public function targetRequirement(): BelongsTo
    {
        return $this->belongsTo(
            NormativeRequirement::class,
            'target_requirement_id'
        );
    }
}
