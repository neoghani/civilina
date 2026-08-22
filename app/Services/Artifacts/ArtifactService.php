<?php

namespace App\Services\Artifacts;

use App\Models\Artifact;
use App\Models\ArtifactRevision;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class ArtifactService
{
    private const TRANSITIONS = [
        Artifact::STATUS_DRAFT => [
            Artifact::STATUS_IN_REVIEW,
            Artifact::STATUS_ARCHIVED,
        ],

        Artifact::STATUS_IN_REVIEW => [
            Artifact::STATUS_DRAFT,
            Artifact::STATUS_APPROVED,
            Artifact::STATUS_ARCHIVED,
        ],

        Artifact::STATUS_APPROVED => [
            Artifact::STATUS_SUPERSEDED,
            Artifact::STATUS_ARCHIVED,
        ],

        Artifact::STATUS_SUPERSEDED => [
            Artifact::STATUS_ARCHIVED,
        ],

        Artifact::STATUS_ARCHIVED => [],
    ];

    public function create(array $data): Artifact
    {
        $this->validateArtifactInput($data);

        return DB::transaction(function () use ($data): Artifact {
            $status = $data['status'] ?? Artifact::STATUS_DRAFT;

            $artifact = Artifact::create([
                'type' => $data['type'],
                'title' => $data['title'],
                'status' => $status,
                'created_by' => $data['created_by'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $revision = $artifact->revisions()->create([
                'revision_number' => 1,
                'revision_label' => $this->revisionLabel(
                    $status,
                    1
                ),
                'status' => $status,
                'payload' => $data['payload'],
                'provenance' => $data['provenance'] ?? null,
                'change_summary' =>
                    $data['change_summary'] ??
                    'Initial artifact creation.',
                'source' => $data['source'] ?? 'civilina_ai',
                'checksum' => $this->checksum(
                    $data['payload']
                ),
                'created_by' => $data['created_by'] ?? null,
            ]);

            $artifact->forceFill([
                'current_revision_id' => $revision->id,
            ])->save();

            return $artifact->fresh([
                'currentRevision',
                'revisions',
            ]);
        });
    }

    public function revise(
        Artifact $artifact,
        array $data
    ): ArtifactRevision {
        if (
            ! array_key_exists('payload', $data) ||
            ! is_array($data['payload'])
        ) {
            throw new InvalidArgumentException(
                'A structured payload is required.'
            );
        }

        $status =
            $data['status'] ??
            Artifact::STATUS_DRAFT;

        $this->validateStatus($status);

        return DB::transaction(
            function () use (
                $artifact,
                $data,
                $status
            ): ArtifactRevision {
                $lockedArtifact = Artifact::query()
                    ->whereKey($artifact->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $nextRevision =
                    ((int) $lockedArtifact
                        ->revisions()
                        ->max('revision_number')) + 1;

                $revision =
                    $lockedArtifact
                        ->revisions()
                        ->create([
                            'revision_number' =>
                                $nextRevision,

                            'revision_label' =>
                                $this->revisionLabel(
                                    $status,
                                    $nextRevision
                                ),

                            'status' =>
                                $status,

                            'payload' =>
                                $data['payload'],

                            'provenance' =>
                                $data['provenance'] ?? null,

                            'change_summary' =>
                                $data['change_summary'] ?? null,

                            'source' =>
                                $data['source'] ??
                                'civilina_ai',

                            'checksum' =>
                                $this->checksum(
                                    $data['payload']
                                ),

                            'created_by' =>
                                $data['created_by'] ??
                                null,
                        ]);

                $lockedArtifact->forceFill([
                    'current_revision_id' =>
                        $revision->id,

                    'status' =>
                        $status,
                ])->save();

                return $revision->fresh();
            }
        );
    }

    public function transition(
        Artifact $artifact,
        string $targetStatus,
        array $context = []
    ): ArtifactRevision {
        $this->validateStatus($targetStatus);

        return DB::transaction(
            function () use (
                $artifact,
                $targetStatus,
                $context
            ): ArtifactRevision {
                $lockedArtifact = Artifact::query()
                    ->with('currentRevision')
                    ->whereKey($artifact->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $currentStatus =
                    $lockedArtifact->status;

                if (
                    ! $this->canTransition(
                        $currentStatus,
                        $targetStatus
                    )
                ) {
                    throw new LogicException(
                        sprintf(
                            'Artifact transition from [%s] to [%s] is not allowed.',
                            $currentStatus,
                            $targetStatus
                        )
                    );
                }

                $currentRevision =
                    $lockedArtifact->currentRevision;

                if (! $currentRevision) {
                    throw new LogicException(
                        'Artifact has no current revision.'
                    );
                }

                $provenance =
                    $currentRevision->provenance ?? [];

                $provenance['lifecycle'] = [
                    'from' => $currentStatus,
                    'to' => $targetStatus,
                    'reason' =>
                        $context['reason'] ?? null,
                ];

                return $this->revise(
                    $lockedArtifact,
                    [
                        'status' =>
                            $targetStatus,

                        'payload' =>
                            $currentRevision->payload,

                        'provenance' =>
                            $provenance,

                        'change_summary' =>
                            $context['change_summary'] ??
                            sprintf(
                                'Lifecycle transition: %s -> %s.',
                                $currentStatus,
                                $targetStatus
                            ),

                        'source' =>
                            $context['source'] ??
                            'civilina_core',

                        'created_by' =>
                            $context['created_by'] ??
                            null,
                    ]
                );
            }
        );
    }

    public function canTransition(
        string $currentStatus,
        string $targetStatus
    ): bool {
        if (
            ! isset(
                self::TRANSITIONS[
                    $currentStatus
                ]
            )
        ) {
            return false;
        }

        return in_array(
            $targetStatus,
            self::TRANSITIONS[
                $currentStatus
            ],
            true
        );
    }

    private function validateArtifactInput(
        array $data
    ): void {
        if (
            empty($data['type']) ||
            ! in_array(
                $data['type'],
                Artifact::TYPES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unsupported artifact type.'
            );
        }

        if (
            empty(
                trim(
                    (string) (
                        $data['title'] ?? ''
                    )
                )
            )
        ) {
            throw new InvalidArgumentException(
                'Artifact title is required.'
            );
        }

        if (
            ! isset($data['payload']) ||
            ! is_array($data['payload'])
        ) {
            throw new InvalidArgumentException(
                'A structured payload is required.'
            );
        }

        $this->validateStatus(
            $data['status'] ??
            Artifact::STATUS_DRAFT
        );
    }

    private function validateStatus(
        string $status
    ): void {
        if (
            ! in_array(
                $status,
                Artifact::STATUSES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unsupported artifact status.'
            );
        }
    }

    private function revisionLabel(
        string $status,
        int $number
    ): string {
        $prefix = match ($status) {
            Artifact::STATUS_APPROVED =>
                'APPROVED',

            Artifact::STATUS_IN_REVIEW =>
                'REVIEW',

            Artifact::STATUS_SUPERSEDED =>
                'SUPERSEDED',

            Artifact::STATUS_ARCHIVED =>
                'ARCHIVED',

            default =>
                'DRAFT',
        };

        return sprintf(
            '%s-%02d',
            $prefix,
            $number
        );
    }

    private function checksum(
        array $payload
    ): string {
        return hash(
            'sha256',
            json_encode(
                $this->canonicalize(
                    $payload
                ),
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
            )
        );
    }

    private function canonicalize(
        array $value
    ): array {
        if (array_is_list($value)) {
            return array_map(
                fn ($item) =>
                    is_array($item)
                        ? $this->canonicalize(
                            $item
                        )
                        : $item,
                $value
            );
        }

        ksort($value);

        foreach (
            $value as
            $key => $item
        ) {
            if (is_array($item)) {
                $value[$key] =
                    $this->canonicalize(
                        $item
                    );
            }
        }

        return $value;
    }
}