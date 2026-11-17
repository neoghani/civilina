<?php

namespace Tests\Feature;

use App\Models\Artifact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtifactApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_api_creates_a_persistent_artifact(): void
    {
        $response = $this->postJson(
            '/api/artifacts',
            [
                'type' =>
                    Artifact::TYPE_RISK_REGISTER,

                'title' =>
                    'Risk Register — SP-01',

                'payload' => [
                    'scope' =>
                        'Projet SP-01',

                    'rows' => [
                        [
                            'id' => 'R-001',
                            'risk' =>
                                'Retard fournisseur',
                        ],
                    ],
                ],

                'provenance' => [
                    'origin' =>
                        'civilina_ai',

                    'invented_facts' =>
                        false,
                ],
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.type',
                Artifact::TYPE_RISK_REGISTER
            )
            ->assertJsonPath(
                'data.status',
                Artifact::STATUS_DRAFT
            )
            ->assertJsonPath(
                'data.current_revision.revision_label',
                'DRAFT-01'
            )
            ->assertJsonPath(
                'data.revision_count',
                1
            );

        $this->assertDatabaseCount(
            'artifacts',
            1
        );

        $this->assertDatabaseCount(
            'artifact_revisions',
            1
        );
    }

    public function test_api_reads_artifact_by_public_id(): void
    {
        $created = $this->postJson(
            '/api/artifacts',
            [
                'type' =>
                    Artifact::TYPE_DASHBOARD,

                'title' =>
                    'Dashboard — SP-01',

                'payload' => [
                    'rows' => [],
                ],
            ]
        );

        $publicId =
            $created->json(
                'data.public_id'
            );

        $this->getJson(
            '/api/artifacts/' .
            $publicId
        )
            ->assertOk()
            ->assertJsonPath(
                'data.public_id',
                $publicId
            )
            ->assertJsonPath(
                'data.title',
                'Dashboard — SP-01'
            );
    }

    public function test_api_creates_new_revision_and_preserves_history(): void
    {
        $created = $this->postJson(
            '/api/artifacts',
            [
                'type' =>
                    Artifact::TYPE_DASHBOARD,

                'title' =>
                    'Dashboard — SP-01',

                'payload' => [
                    'kpi' => 68,
                ],
            ]
        );

        $publicId =
            $created->json(
                'data.public_id'
            );

        $this->postJson(
            '/api/artifacts/' .
            $publicId .
            '/revisions',
            [
                'payload' => [
                    'kpi' => 72,
                ],

                'change_summary' =>
                    'Weekly update.',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.revision_number',
                2
            )
            ->assertJsonPath(
                'data.revision_label',
                'DRAFT-02'
            );

        $this->getJson(
            '/api/artifacts/' .
            $publicId .
            '/revisions'
        )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data'
            );
    }

    public function test_api_performs_valid_lifecycle_transition(): void
    {
        $created = $this->postJson(
            '/api/artifacts',
            [
                'type' =>
                    Artifact::TYPE_RACI,

                'title' =>
                    'RACI — SP-01',

                'payload' => [
                    'rows' => [],
                ],
            ]
        );

        $publicId =
            $created->json(
                'data.public_id'
            );

        $this->postJson(
            '/api/artifacts/' .
            $publicId .
            '/transitions',
            [
                'status' =>
                    Artifact::STATUS_IN_REVIEW,

                'reason' =>
                    'Ready for review.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                Artifact::STATUS_IN_REVIEW
            )
            ->assertJsonPath(
                'data.revision_label',
                'REVIEW-02'
            );

        $this->getJson(
            '/api/artifacts/' .
            $publicId
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                Artifact::STATUS_IN_REVIEW
            )
            ->assertJsonPath(
                'data.revision_count',
                2
            );
    }

    public function test_api_rejects_invalid_lifecycle_transition(): void
    {
        $created = $this->postJson(
            '/api/artifacts',
            [
                'type' =>
                    Artifact::TYPE_WORKFLOW,

                'title' =>
                    'Workflow — SP-01',

                'payload' => [
                    'rows' => [],
                ],
            ]
        );

        $publicId =
            $created->json(
                'data.public_id'
            );

        $this->postJson(
            '/api/artifacts/' .
            $publicId .
            '/transitions',
            [
                'status' =>
                    Artifact::STATUS_APPROVED,
            ]
        )
            ->assertStatus(422);

        $this->assertDatabaseCount(
            'artifact_revisions',
            1
        );
    }
}