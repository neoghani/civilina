<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artifact;
use App\Models\Organization;
use App\Services\Artifacts\ArtifactTenantAccessService;
use App\Services\Organizations\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationArtifactController extends Controller
{
    public function index(
        Request $request,
        Organization $organization,
        OrganizationAccessService $organizationAccess,
        ArtifactTenantAccessService $artifactAccess
    ): JsonResponse {
        abort_unless(
            $organizationAccess
                ->hasActiveMembership(
                    $request->user(),
                    $organization
                ),
            403
        );

        $artifacts = $artifactAccess
            ->visibleArtifactsFor(
                $request->user(),
                $organization
            )
            ->map(
                fn (Artifact $artifact): array =>
                    $this->serializeArtifact(
                        $artifact
                    )
            )
            ->values();

        return response()->json([
            'data' => $artifacts,
        ]);
    }

    public function store(
        Request $request,
        Organization $organization,
        OrganizationAccessService $organizationAccess,
        ArtifactTenantAccessService $artifactAccess
    ): JsonResponse {
        abort_unless(
            $organizationAccess->can(
                $request->user(),
                $organization,
                'edit_artifacts'
            ),
            403
        );

        return DB::transaction(
            function () use (
                $request,
                $organization,
                $artifactAccess
            ): JsonResponse {
                /*
                 * Reuse the proven Sprint 3 artifact creation contract.
                 * app()->call() lets Laravel inject any dependencies
                 * required by ArtifactController::store without
                 * duplicating its validation or revision logic here.
                 */
                $artifactController =
                    app(ArtifactController::class);

                $response = app()->call(
                    [
                        $artifactController,
                        'store',
                    ],
                    [
                        'request' => $request,
                    ]
                );

                if (! $response instanceof JsonResponse) {
                    abort(
                        500,
                        'Artifact creation returned an invalid response.'
                    );
                }

                $body = $response->getData(
                    true
                );

                $publicId =
                    $body['data']['public_id']
                    ?? null;

                if (! is_string($publicId) || $publicId === '') {
                    abort(
                        500,
                        'Artifact creation did not return a public id.'
                    );
                }

                $artifact = Artifact::query()
                    ->where(
                        'public_id',
                        $publicId
                    )
                    ->firstOrFail();

                $artifact =
                    $artifactAccess
                        ->assignToOrganization(
                            $artifact,
                            $organization
                        );

                $body['data']['organization_id'] =
                    $artifact->organization_id;

                return response()->json(
                    $body,
                    $response->getStatusCode()
                );
            }
        );
    }

    public function show(
        Request $request,
        Organization $organization,
        Artifact $artifact,
        OrganizationAccessService $organizationAccess,
        ArtifactTenantAccessService $artifactAccess
    ): JsonResponse {
        /*
         * Do not disclose whether an artifact exists
         * in another tenant.
         */
        abort_unless(
            $artifactAccess
                ->belongsToOrganization(
                    $artifact,
                    $organization
                ),
            404
        );

        abort_unless(
            $organizationAccess
                ->hasActiveMembership(
                    $request->user(),
                    $organization
                ),
            403
        );

        abort_unless(
            $artifactAccess->canView(
                $request->user(),
                $organization,
                $artifact
            ),
            403
        );

        $artifact->load(
            'currentRevision'
        );

        return response()->json([
            'data' => $this->serializeArtifact(
                $artifact
            ),
        ]);
    }

    private function serializeArtifact(
        Artifact $artifact
    ): array {
        return [
            'public_id' => $artifact->public_id,
            'organization_id' =>
                $artifact->organization_id,
            'type' => $artifact->type,
            'title' => $artifact->title,
            'status' => $artifact->status,
            'current_revision' =>
                $artifact->relationLoaded(
                    'currentRevision'
                ) &&
                $artifact->currentRevision
                    ? [
                        'revision_label' =>
                            $artifact
                                ->currentRevision
                                ->revision_label,
                    ]
                    : null,
        ];
    }
}
