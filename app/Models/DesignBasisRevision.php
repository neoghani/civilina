<?php

namespace App\Models;

use App\Enums\EngineeringRevisionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DesignBasisRevision extends Model
{
    protected $fillable = [
        'project_id',
        'revision_no',
        'status',
        'title',
        'change_summary',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'revision_no' => 'integer',
            'status' => EngineeringRevisionStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(DesignBasisItem::class);
    }
}
