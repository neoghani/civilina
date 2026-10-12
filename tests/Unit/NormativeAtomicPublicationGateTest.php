<?php

namespace Tests\Unit;

use App\Services\Engineering\NormativeLibrarian\NormativeAtomicPublicationGateService;
use PHPUnit\Framework\TestCase;

class NormativeAtomicPublicationGateTest extends TestCase
{
    private NormativeAtomicPublicationGateService $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = new NormativeAtomicPublicationGateService();
    }

    public function test_nf_en_206_unknown_edition_remains_not_publishable(): void
    {
        $row = $this->validStatement();
        $row->reference_code = 'NF EN 206';
        $row->edition_key = 'NFEN206__ANNEE_INCONNUE';

        $this->assertSame('BLOCKED_EDITION', $this->gate->evaluate($row)['code']);
    }

    public function test_secondary_detected_is_not_publishable_even_with_tampered_primary_decision(): void
    {
        $row = $this->validStatement();
        $row->source_detected_authority = 'SECONDARY_DETECTED';

        $this->assertSame('BLOCKED_SOURCE', $this->gate->evaluate($row)['code']);
    }

    public function test_primary_candidate_requires_human_source_validation(): void
    {
        $row = $this->validStatement();
        $row->source_review_status = 'UNREVIEWED';
        $row->source_reviewed_at = null;

        $this->assertSame('BLOCKED_SOURCE', $this->gate->evaluate($row)['code']);
    }

    public function test_primary_validated_with_unvalidated_statement_is_not_publishable(): void
    {
        $row = $this->validStatement();
        $row->review_status = 'CANDIDATE';
        $row->reviewed_at = null;

        $this->assertSame('BLOCKED_STATEMENT', $this->gate->evaluate($row)['code']);
    }

    public function test_primary_validated_requirement_with_known_edition_is_eligible(): void
    {
        $result = $this->gate->evaluate($this->validStatement());

        $this->assertTrue($result['eligible']);
        $this->assertSame('ELIGIBLE', $result['code']);
    }

    public function test_reference_value_is_not_publishable(): void
    {
        $row = $this->validStatement();
        $row->statement_type = 'REFERENCE_VALUE';

        $this->assertSame('BLOCKED_TYPE', $this->gate->evaluate($row)['code']);
    }

    public function test_formula_table_is_not_publishable(): void
    {
        $row = $this->validStatement();
        $row->statement_type = 'FORMULA_TABLE';

        $this->assertSame('BLOCKED_TYPE', $this->gate->evaluate($row)['code']);
    }

    private function validStatement(): object
    {
        return (object) [
            'id' => 1,
            'deep_document_id' => 10,
            'document_id' => 20,
            'section_id' => 30,
            'reference_code' => 'NF TEST 100',
            'edition_key' => '2025',
            'statement_type' => 'REQUIREMENT',
            'statement_text' => 'Le controle doit etre enregistre.',
            'review_status' => 'VALIDATED',
            'reviewed_at' => '2026-09-13 18:00:00',
            'source_review_status' => 'PRIMARY_VALIDATED',
            'source_detected_authority' => 'PRIMARY_CANDIDATE',
            'source_reviewed_at' => '2026-09-13 17:00:00',
            'source_hash' => str_repeat('a', 64),
            'statement_hash' => str_repeat('b', 64),
            'provenance_hash' => str_repeat('c', 64),
            'revoked_at' => null,
        ];
    }
}
