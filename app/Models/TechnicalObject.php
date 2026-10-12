<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TechnicalObject extends Model
{
    protected $fillable = [
        'project_id',
        'parent_id',
        'wbs_node_id',
        'work_package_id',
        'activity_id',
        'zone_id',
        'ouvrage_id',
        'code',
        'name',
        'object_type',
        'discipline',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'parent_id'
        );
    }

    public function children(): HasMany
    {
        return $this->hasMany(
            self::class,
            'parent_id'
        );
    }

    public function wbsNode(): BelongsTo
    {
        return $this->belongsTo(WbsNode::class);
    }

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
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

    public function revisions(): HasMany
    {
        return $this->hasMany(
            TechnicalObjectRevision::class
        );
    }
}
