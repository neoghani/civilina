<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
        ];
    }

    // URLs carry the public UUID, never the sequential id (no enumeration of
    // firms or projects).
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if (! $project->public_id) {
                $project->public_id = (string) Str::uuid();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function wbsNodes(): HasMany
    {
        return $this->hasMany(WbsNode::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(ProjectZone::class);
    }

    public function ouvrages(): HasMany
    {
        return $this->hasMany(ProjectOuvrage::class);
    }

    public function workPackages(): HasMany
    {
        return $this->hasMany(WorkPackage::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function designBasisRevisions(): HasMany
    {
        return $this->hasMany(DesignBasisRevision::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function technicalObjects(): HasMany
    {
        return $this->hasMany(TechnicalObject::class);
    }

    public function scopeForOrganization(
        Builder $query,
        Organization $organization
    ): Builder {
        return $query->where(
            'organization_id',
            $organization->getKey()
        );
    }

    /**
     * Profils normatifs associÃ©s au projet.
     */
    public function standardProfiles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ProjectStandardProfile::class);
    }
}
