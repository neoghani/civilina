<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Member = 'member';
    case Viewer = 'viewer';

    public function allows(string $ability): bool
    {
        return match ($ability) {
            'manage_organization', 'manage_members', 'manage_roles' => in_array($this, [self::Owner, self::Admin], true),
            'manage_owners' => $this === self::Owner,
            'view_artifacts' => true,
            'edit_artifacts' => in_array($this, [self::Owner, self::Admin, self::Manager, self::Member], true),
            'approve_artifacts', 'archive_artifacts' => in_array($this, [self::Owner, self::Admin, self::Manager], true),
            default => false,
        };
    }
}
