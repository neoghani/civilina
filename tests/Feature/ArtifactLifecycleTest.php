<?php

namespace Tests\Feature;

use App\Models\Artifact;
use App\Services\Artifacts\ArtifactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ArtifactLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_artifact_can_move_from_draft_to_review(): void
    {
        $service =
            app(ArtifactService::class);

        $artifact =
            $service->create([
                'type' =>
                    Artifact::TYPE_ITP,

                'title' =>
                    'ITP — SP-01',

                'payload' => [
                    'rows' => [],
                ],
            ]);

        $revision =
            $service->transition(
                $artifact,
                Artifact::STATUS_IN_REVIEW,
                [
                    'reason' =>
                        'Ready for QA review.',
                ]
            );

        $artifact->refresh();

        $this->assertSame(
            Artifact::STATUS_IN_REVIEW,
            $artifact->status
        );

        $this->assertSame(
            'REVIEW-02',
            $revision->revision_label
        );

        $this->assertSame(
            Artifact::STATUS_IN_REVIEW,
            $revision->status
        );

        $this->assertSame(
            'draft',
            $revision
                ->provenance
                ['lifecycle']
                ['from']
        );

        $this->assertSame(
            'in_review',
            $revision
                ->provenance
                ['lifecycle']
                ['to']
        );
    }

    public function test_reviewed_artifact_can_be_approved(): void
    {
        $service =
            app(ArtifactService::class);

        $artifact =
            $service->create([
                'type' =>
                    Artifact::TYPE_RACI,

                'title' =>
                    'RACI — SP-01',

                'payload' => [
                    'rows' => [],
                ],
            ]);

        $service->transition(
            $artifact,
            Artifact::STATUS_IN_REVIEW
        );

        $artifact->refresh();

        $approved =
            $service->transition(
                $artifact,
                Artifact::STATUS_APPROVED,
                [
                    'reason' =>
                        'Validated by project management.',
                ]
            );

        $artifact->refresh();

        $this->assertSame(
            Artifact::STATUS_APPROVED,
            $artifact->status
        );

        $this->assertSame(
            'APPROVED-03',
            $approved->revision_label
        );

        $this->assertCount(
            3,
            $artifact
                ->revisions()
                ->get()
        );
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $service =
            app(ArtifactService::class);

        $artifact =
            $service->create([
                'type' =>
                    Artifact::TYPE_DASHBOARD,

                'title' =>
                    'Dashboard — SP-01',

                'payload' => [
                    'rows' => [],
                ],
            ]);

        $this->expectException(
            LogicException::class
        );

        $service->transition(
            $artifact,
            Artifact::STATUS_APPROVED
        );
    }

    public function test_archived_artifact_cannot_transition_again(): void
    {
        $service =
            app(ArtifactService::class);

        $artifact =
            $service->create([
                'type' =>
                    Artifact::TYPE_WORKFLOW,

                'title' =>
                    'Workflow — SP-01',

                'payload' => [
                    'rows' => [],
                ],
            ]);

        $service->transition(
            $artifact,
            Artifact::STATUS_ARCHIVED
        );

        $artifact->refresh();

        $this->assertSame(
            Artifact::STATUS_ARCHIVED,
            $artifact->status
        );

        $this->expectException(
            LogicException::class
        );

        $service->transition(
            $artifact,
            Artifact::STATUS_DRAFT
        );
    }
}