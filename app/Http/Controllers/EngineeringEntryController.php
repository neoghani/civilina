<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationMembershipStatus;
use App\Models\OrganizationMembership;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EngineeringEntryController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        $membership = OrganizationMembership::query()
            ->where('user_id', $user->id)
            ->where(
                'status',
                OrganizationMembershipStatus::Active->value
            )
            ->orderBy('id')
            ->first();

        if (! $membership) {
            return redirect('/')
                ->with(
                    'engineering_entry_error',
                    'Aucune organisation Civilina active.'
                );
        }

        $project = Project::query()
            ->where(
                'organization_id',
                $membership->organization_id
            )
            ->orderBy('id')
            ->first();

        if (! $project) {
            return redirect('/')
                ->with(
                    'engineering_entry_error',
                    'Aucun projet Engineering disponible.'
                );
        }

        return redirect()->route(
            'engineering.workspace',
            [
                'organization' => $membership->organization->public_id,
                'project' => $project->public_id,
            ]
        );
    }
}
