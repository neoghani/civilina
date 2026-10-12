<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngineeringNormativeSection extends Model
{
    protected $table = 'engineering_normative_sections_v2';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function edition(): BelongsTo
    {
        return $this->belongsTo(EngineeringNormativeEdition::class, 'edition_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(EngineeringNormativeSource::class, 'source_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(EngineeringNormativeRequirement::class, 'section_id');
    }
}
