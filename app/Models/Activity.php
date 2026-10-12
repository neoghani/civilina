<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Activity extends Model
{
    protected $fillable = [
        'project_id',
        'work_package_id',
        'zone_id',
        'ouvrage_id',
        'code',
        'name',
        'description',
        'activity_type',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => ActivityStatus::class,
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Activity $activity): void {
            if (! $activity->public_id) {
                $activity->public_id = (string) Str::uuid();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(
            ProjectZone::class,
            'zone_id'
        );
    }

    public function ouvrage(): BelongsTo
    {
        return $this->belongsTo(
            ProjectOuvrage::class,
            'ouvrage_id'
        );
    }

    public function technicalObjects(): HasMany
    {
        return $this->hasMany(TechnicalObject::class);
    }
}
