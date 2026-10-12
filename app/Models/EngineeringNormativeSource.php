<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngineeringNormativeSource extends Model
{
    protected $table = 'engineering_normative_sources';

    protected $guarded = [];

    protected $casts = [
        'source_modified_at' => 'datetime',
        'indexed_at' => 'datetime',
        'is_exact_duplicate' => 'boolean',
        'metadata' => 'array',
    ];

    public function standard(): BelongsTo
    {
        return $this->belongsTo(EngineeringNormativeStandard::class, 'standard_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_source_id');
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'duplicate_of_source_id');
    }
}
