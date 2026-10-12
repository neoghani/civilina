<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngineeringNormativeStandard extends Model
{
    protected $table = 'engineering_normative_standards';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function sources(): HasMany
    {
        return $this->hasMany(EngineeringNormativeSource::class, 'standard_id');
    }

    public function editions(): HasMany
    {
        return $this->hasMany(EngineeringNormativeEdition::class, 'standard_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(EngineeringQaNormativeLink::class, 'standard_id');
    }
}
