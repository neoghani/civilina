<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\Engineering\EngineeringTenantAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class EngineeringWorkspaceController extends Controller
{
    public function show(
        Request $request,
        Organization $organization,
        string $project,
        EngineeringTenantAccessService $access
    ): View {
        $membership =
            $access->authorizeView(
                $request->user(),
                $organization
            );

        $projectModel =
            $access->scopedProject(
                $organization,
                $project
            );

        $role =
            is_object($membership->role) &&
            property_exists(
                $membership->role,
                'value'
            )
                ? $membership->role->value
                : (string) $membership->role;

        return view(
            'engineering.workspace',
            [
                'organization' => $organization,
                'project' => $projectModel,
                'role' => $role,
                'canEdit' => in_array(
                    $role,
                    [
                        'owner',
                        'admin',
                        'manager',
                        'member',
                    ],
                    true
                ),
                'canApprove' => in_array(
                    $role,
                    [
                        'owner',
                        'admin',
                        'manager',
                    ],
                    true
                ),
            ]
        );
    }
}
