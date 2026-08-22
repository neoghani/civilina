<?php

namespace Tests\Feature;

use App\Models\Artifact;
use App\Models\ArtifactRevision;
use App\Models\User;
use App\Services\Artifacts\ArtifactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ArtifactCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_artifact_with_initial_revision(): void
    {
        $user = User::factory()->create();

        $service = app(ArtifactService::class);

        $artifact = $service->create([
            'type' => Artifact::TYPE_RISK_REGISTER,

            'title' =>
                'Risk Register — SP-01',

            'created_by' =>
                $user->id,

            'owner_id' =>
                $user->id,

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
        ]);

        $this->assertNotNull(
            $artifact->public_id
        );

        $this->assertSame(
            Artifact::TYPE_RISK_REGISTER,
            $artifact->type
        );

        $this->assertSame(
            Artifact::STATUS_DRAFT,
            $artifact->status
        );

        $this->assertNotNull(
            $artifact->current_revision_id
        );

        $this->assertCount(
            1,
            $artifact->revisions
        );

        $this->assertSame(
            'DRAFT-01',
            $artifact
                ->currentRevision
                ->revision_label
        );

        $this->assertSame(
            'R-001',
            $artifact
                ->currentRevision
                ->payload['rows'][0]['id']
        );

        $this->assertSame(
            false,
            $artifact
                ->currentRevision
                ->provenance['invented_facts']
        );

        $this->assertSame(
            64,
            strlen(
                $artifact
                    ->currentRevision
                    ->checksum
            )
        );
    }

    public function test_it_creates_a_new_revision_without_overwriting_the_previous_one(): void
    {
        $service =
            app(ArtifactService::class);

        $artifact =
            $service->create([
                'type' =>
                    Artifact::TYPE_DASHBOARD,

                'title' =>
                    'Executive Dashboard — SP-01',

                'payload' => [
                    'rows' => [
                        [
                            'id' =>
                                'KPI-001',

                            'value' =>
                                '68',
                        ],
                    ],
                ],
            ]);

        $firstRevisionId =
            $artifact
                ->currentRevision
                ->id;

        $second =
            $service->revise(
                $artifact,
                [
                    'payload' => [
                        'rows' => [
                            [
                                'id' =>
                                    'KPI-001',

                                'value' =>
                                    '72',
                            ],
                        ],
                    ],

                    'change_summary' =>
                        'Updated weekly progress.',
                ]
            );

        $artifact->refresh();

        $this->assertSame(
            2,
            $second->revision_number
        );

        $this->assertSame(
            'DRAFT-02',
            $second->revision_label
        );

        $this->assertSame(
            $second->id,
            $artifact->current_revision_id
        );

        $this->assertDatabaseHas(
            'artifact_revisions',
            [
                'id' =>
                    $firstRevisionId,

                'revision_number' =>
                    1,
            ]
        );

        $this->assertDatabaseHas(
            'artifact_revisions',
            [
                'id' =>
                    $second->id,

                'revision_number' =>
                    2,
            ]
        );
    }

    public function test_existing_revisions_are_immutable(): void
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

        $revision =
            ArtifactRevision::findOrFail(
                $artifact->current_revision_id
            );

        $this->expectException(
            LogicException::class
        );

        $revision->update([
            'change_summary' =>
                'Illegal mutation',
        ]);
    }
}