<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineeringNormativeRequirement extends Model
{
    protected $table = 'engineering_normative_requirements';

    protected $guarded = [];

    protected $casts = [
        'keywords' => 'array',
        'min_value' => 'decimal:6',
        'max_value' => 'decimal:6',
        'nominal_value' => 'decimal:6',
        'tolerance_plus' => 'decimal:6',
        'tolerance_minus' => 'decimal:6',
        'extraction_confidence' => 'decimal:2',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(EngineeringNormativeSection::class, 'section_id');
    }
}
