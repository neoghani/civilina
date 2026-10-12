<?php

namespace App\Models;

use App\Enums\EngineeringFindingSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineeringFinding extends Model
{
    protected $fillable = [
        'engineering_check_id',
        'severity',
        'code',
        'title',
        'description',
        'recommended_action',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'severity' => EngineeringFindingSeverity::class,
        ];
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(
            EngineeringCheck::class,
            'engineering_check_id'
        );
    }
}
