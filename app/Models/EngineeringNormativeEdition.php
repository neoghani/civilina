<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngineeringNormativeEdition extends Model
{
    protected $table = 'engineering_normative_editions';

    protected $guarded = [];

    protected $casts = [
        'is_current' => 'boolean',
        'metadata' => 'array',
    ];

    public function standard(): BelongsTo
    {
        return $this->belongsTo(EngineeringNormativeStandard::class, 'standard_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(EngineeringNormativeSource::class, 'source_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(EngineeringNormativeSection::class, 'edition_id');
    }
}
