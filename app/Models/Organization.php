<?php

namespace App\Models;

use App\Enums\OrganizationMembershipStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            if (! $organization->public_id) {
                $organization->public_id = (string) Str::uuid();
            }
        });
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'organization_memberships'
        )
            ->using(OrganizationMembership::class)
            ->withPivot([
                'id',
                'role',
                'status',
                'joined_at',
            ])
            ->withTimestamps();
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }

    public function scopeVisibleTo(
        Builder $query,
        User $user
    ): Builder {
        return $query->whereHas(
            'memberships',
            function (Builder $membershipQuery) use ($user): void {
                $membershipQuery
                    ->where('user_id', $user->getKey())
                    ->where(
                        'status',
                        OrganizationMembershipStatus::Active->value
                    );
            }
        );
    }
}
