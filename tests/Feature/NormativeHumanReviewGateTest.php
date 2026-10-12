<?php

namespace Tests\Feature;

use App\Services\Engineering\NormativeLibrarian\NormativeAtomicStatementReviewService;
use App\Services\Engineering\NormativeLibrarian\NormativeSourceReviewService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NormativeHumanReviewGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_edition_cannot_become_primary_validated(): void
    {
        $this->insertSource('BLOCKED_EDITION', 'NFEN206__ANNEE_INCONNUE');

        $this->expectException(DomainException::class);
        app(NormativeSourceReviewService::class)->decide(10, 'PRIMARY_VALIDATED', 'Controle humain.', null, 'QA');
    }

    public function test_primary_candidate_decision_is_human_and_historized(): void
    {
        $this->insertSource('PRIMARY_CANDIDATE', '2025');
        app(NormativeSourceReviewService::class)->decide(10, 'PRIMARY_VALIDATED', 'Couverture verifiee.', null, 'QA');

        $this->assertDatabaseHas('civilina_normative_atomic_source_reviews', [
            'deep_document_id' => 10,
            'review_status' => 'PRIMARY_VALIDATED',
            'reviewed_by_name' => 'QA',
        ]);
        $this->assertDatabaseHas('civilina_normative_source_review_events', [
            'deep_document_id' => 10,
            'decision' => 'PRIMARY_VALIDATED',
        ]);
    }

    public function test_rejected_statement_requires_a_reason(): void
    {
        $this->expectException(DomainException::class);
        app(NormativeAtomicStatementReviewService::class)->decide(99, 'REJECTED', '');
    }

    private function insertSource(string $authority, string $edition): void
    {
        DB::table('civilina_normative_atomic_source_reviews')->insert([
            'deep_document_id' => 10,
            'document_id' => 20,
            'reference_code' => 'NF EN 206',
            'edition_key' => $edition,
            'detected_authority' => $authority,
            'detected_reason' => 'Fixture locale.',
            'review_status' => 'UNREVIEWED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
