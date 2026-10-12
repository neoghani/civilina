<?php

namespace App\Models;

use App\Enums\WorkPackageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WorkPackage extends Model
{
    protected $fillable = [
        'project_id',
        'wbs_node_id',
        'zone_id',
        'ouvrage_id',
        'code',
        'name',
        'description',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkPackageStatus::class,
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkPackage $workPackage): void {
            if (! $workPackage->public_id) {
                $workPackage->public_id = (string) Str::uuid();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function wbsNode(): BelongsTo
    {
        return $this->belongsTo(WbsNode::class);
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

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function technicalObjects(): HasMany
    {
        return $this->hasMany(TechnicalObject::class);
    }
}
