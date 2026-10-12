<?php

namespace App\Models;

use App\Enums\EngineeringRevisionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalObjectRevision extends Model
{
    protected $fillable = [
        'technical_object_id',
        'revision_no',
        'status',
        'material_id',
        'geometry',
        'properties',
        'assumptions',
        'provenance',
        'change_summary',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'revision_no' => 'integer',
            'status' => EngineeringRevisionStatus::class,
            'geometry' => 'array',
            'properties' => 'array',
            'assumptions' => 'array',
            'provenance' => 'array',
        ];
    }

    public function technicalObject(): BelongsTo
    {
        return $this->belongsTo(TechnicalObject::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }
}
