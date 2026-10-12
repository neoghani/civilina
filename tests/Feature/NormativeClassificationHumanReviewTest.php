<?php

namespace Tests\Feature;

use App\Services\Engineering\NormativeLibrarian\NormativeClassificationReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NormativeClassificationHumanReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_human_can_accept_a_suggested_family_once(): void
    {
        $id = $this->classification('Béton');

        app(NormativeClassificationReviewService::class)->decide(
            $id,
            'CLASSIFIED',
            'Famille vérifiée sur la source locale.',
            'QA_TEST'
        );

        $this->assertDatabaseHas('civilina_normative_classifications', [
            'id' => $id,
            'family' => 'Béton',
            'classification_status' => 'CLASSIFIED',
            'reviewed_by_name' => 'QA_TEST',
        ]);
    }

    public function test_missing_suggestion_cannot_be_accepted_automatically(): void
    {
        $id = $this->classification(null);

        $this->expectException(\DomainException::class);
        app(NormativeClassificationReviewService::class)->decide(
            $id,
            'CLASSIFIED',
            'Tentative sans famille proposée.',
            'QA_TEST'
        );
    }

    private function classification(?string $suggestedFamily): int
    {
        return DB::table('civilina_normative_classifications')->insertGetId([
            'target_type' => 'DOCUMENT',
            'target_id' => random_int(1000, 9000),
            'suggested_family' => $suggestedFamily,
            'classification_status' => 'TO_REVIEW',
            'evidence' => '[]',
            'source_fields' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
