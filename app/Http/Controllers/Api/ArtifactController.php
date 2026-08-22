<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artifact;
use App\Models\ArtifactRevision;
use App\Services\Artifacts\ArtifactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LogicException;

class ArtifactController extends Controller
{
    public function store(
        Request $request,
        ArtifactService $service
    ): JsonResponse {
        $validated = $request->validate([
            'type' => [
                'required',
                'string',
                Rule::in(Artifact::TYPES),
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'payload' => [
                'required',
                'array',
            ],

            'provenance' => [
                'nullable',
                'array',
            ],

            'metadata' => [
                'nullable',
                'array',
            ],

            'change_summary' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'source' => [
                'nullable',
                'string',
                'max:50',
            ],

            'created_by' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'owner_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
        ]);

        $artifact = $service->create($validated);

        return response()->json([
            'data' => $this->artifactData($artifact),
        ], 201);
    }

    public function show(
        Artifact $artifact
    ): JsonResponse {
        $artifact->load([
            'currentRevision',
            'revisions',
        ]);

        return response()->json([
            'data' => $this->artifactData($artifact),
        ]);
    }

    public function revisions(
        Artifact $artifact
    ): JsonResponse {
        $revisions = $artifact
            ->revisions()
            ->get();

        return response()->json([
            'data' => $revisions
                ->map(
                    fn (ArtifactRevision $revision) =>
                        $this->revisionData($revision)
                )
                ->values(),
        ]);
    }

    public function revise(
        Request $request,
        Artifact $artifact,
        ArtifactService $service
    ): JsonResponse {
        $validated = $request->validate([
            'payload' => [
                'required',
                'array',
            ],

            'provenance' => [
                'nullable',
                'array',
            ],

            'change_summary' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'source' => [
                'nullable',
                'string',
                'max:50',
            ],

            'created_by' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
        ]);

        $revision = $service->revise(
            $artifact,
            $validated
        );

        return response()->json([
            'data' => $this->revisionData($revision),
        ], 201);
    }

    public function transition(
        Request $request,
        Artifact $artifact,
        ArtifactService $service
    ): JsonResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in(Artifact::STATUSES),
            ],

            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'change_summary' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'source' => [
                'nullable',
                'string',
                'max:50',
            ],

            'created_by' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
        ]);

        try {
            $revision = $service->transition(
                $artifact,
                $validated['status'],
                $validated
            );
        } catch (LogicException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $this->revisionData($revision),
        ]);
    }

    private function artifactData(
        Artifact $artifact
    ): array {
        $artifact->loadMissing([
            'currentRevision',
            'revisions',
        ]);

        return [
            'public_id' =>
                $artifact->public_id,

            'type' =>
                $artifact->type,

            'title' =>
                $artifact->title,

            'status' =>
                $artifact->status,

            'metadata' =>
                $artifact->metadata,

            'current_revision' =>
                $artifact->currentRevision
                    ? $this->revisionData(
                        $artifact->currentRevision
                    )
                    : null,

            'revision_count' =>
                $artifact->revisions->count(),

            'created_at' =>
                $artifact->created_at?->toISOString(),

            'updated_at' =>
                $artifact->updated_at?->toISOString(),
        ];
    }

    private function revisionData(
        ArtifactRevision $revision
    ): array {
        return [
            'public_id' =>
                $revision->public_id,

            'revision_number' =>
                $revision->revision_number,

            'revision_label' =>
                $revision->revision_label,

            'status' =>
                $revision->status,

            'payload' =>
                $revision->payload,

            'provenance' =>
                $revision->provenance,

            'change_summary' =>
                $revision->change_summary,

            'source' =>
                $revision->source,

            'checksum' =>
                $revision->checksum,

            'created_at' =>
                $revision->created_at?->toISOString(),
        ];
    }
}