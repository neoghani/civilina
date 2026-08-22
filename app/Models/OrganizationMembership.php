<?php

namespace App\Models;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OrganizationMembership extends Pivot
{
    protected $table = 'organization_memberships';
    public $incrementing = true;

    protected $fillable = ['organization_id', 'user_id', 'role', 'status', 'joined_at'];

    protected function casts(): array
    {
        return [
            'role' => OrganizationRole::class,
            'status' => OrganizationMembershipStatus::class,
            'joined_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationMembershipStatus::Active;
    }

    public function allows(string $ability): bool
    {
        return $this->isActive() && $this->role->allows($ability);
    }
}
