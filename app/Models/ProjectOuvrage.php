<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectOuvrage extends Model
{
    protected $fillable = [
        'project_id',
        'code',
        'name',
        'description',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workPackages(): HasMany
    {
        return $this->hasMany(
            WorkPackage::class,
            'ouvrage_id'
        );
    }

    public function activities(): HasMany
    {
        return $this->hasMany(
            Activity::class,
            'ouvrage_id'
        );
    }

    public function technicalObjects(): HasMany
    {
        return $this->hasMany(
            TechnicalObject::class,
            'ouvrage_id'
        );
    }
}
