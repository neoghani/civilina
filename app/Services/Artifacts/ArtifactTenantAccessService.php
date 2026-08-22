<?php

namespace App\Services\Artifacts;

use App\Models\Artifact;
use App\Models\Organization;
use App\Models\User;
use App\Services\Organizations\OrganizationAccessService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

class ArtifactTenantAccessService
{
    public function __construct(
        private readonly OrganizationAccessService $organizationAccess
    ) {
    }

    public function assignToOrganization(
        Artifact $artifact,
        Organization $organization
    ): Artifact {
        if (
            $artifact->organization_id !== null &&
            (int) $artifact->organization_id !==
                (int) $organization->getKey()
        ) {
            throw new DomainException(
                'Artifact already belongs to another organization.'
            );
        }

        if ($artifact->organization_id === null) {
            $artifact->organization_id =
                $organization->getKey();

            $artifact->save();
        }

        return $artifact->refresh();
    }

    public function visibleArtifactsFor(
        User $user,
        Organization $organization
    ): Collection {
        if (
            ! $this->organizationAccess
                ->hasActiveMembership(
                    $user,
                    $organization
                )
        ) {
            return new Collection();
        }

        return Artifact::query()
            ->where(
                'organization_id',
                $organization->getKey()
            )
            ->latest('id')
            ->get();
    }

    public function canView(
        User $user,
        Organization $organization,
        Artifact $artifact
    ): bool {
        return $this->belongsToOrganization(
            $artifact,
            $organization
        ) &&
        $this->organizationAccess
            ->hasActiveMembership(
                $user,
                $organization
            );
    }

    public function canEdit(
        User $user,
        Organization $organization,
        Artifact $artifact
    ): bool {
        return $this->belongsToOrganization(
            $artifact,
            $organization
        ) &&
        $this->organizationAccess->can(
            $user,
            $organization,
            'edit_artifacts'
        );
    }

    public function canApprove(
        User $user,
        Organization $organization,
        Artifact $artifact
    ): bool {
        return $this->belongsToOrganization(
            $artifact,
            $organization
        ) &&
        $this->organizationAccess->can(
            $user,
            $organization,
            'approve_artifacts'
        );
    }

    public function belongsToOrganization(
        Artifact $artifact,
        Organization $organization
    ): bool {
        return
            $artifact->organization_id !== null &&
            (int) $artifact->organization_id ===
                (int) $organization->getKey();
    }
}
