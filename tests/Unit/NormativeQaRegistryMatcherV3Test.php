<?php

namespace Tests\Unit;

use App\Services\Engineering\NormativeLibrarian\NormativeClassificationPreparationService;
use App\Services\Engineering\NormativeLibrarian\NormativeQaRegistryMatcherV3;
use PHPUnit\Framework\TestCase;

class NormativeQaRegistryMatcherV3Test extends TestCase
{
    private NormativeQaRegistryMatcherV3 $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new NormativeQaRegistryMatcherV3(
            new NormativeClassificationPreparationService()
        );
    }

    public function test_concrete_never_creates_a_welding_link(): void
    {
        $result = $this->matcher->match(
            $this->inspection('concrete', 'Contrôle béton frais et slump'),
            $this->registry('Soudage', 'Les joints de soudage doivent être inspectés.')
        );

        $this->assertSame('NO_MATCH', $result['result_status']);
        $this->assertStringContainsString('incompatibles', $result['justification']);
    }

    public function test_welding_never_creates_a_concrete_link(): void
    {
        $result = $this->matcher->match(
            $this->inspection('welding', 'Inspection du joint de soudage'),
            $this->registry('Béton', 'Le béton frais doit être soumis à un essai slump.')
        );

        $this->assertSame('NO_MATCH', $result['result_status']);
    }

    public function test_no_match_is_a_normal_result_when_domain_is_unknown(): void
    {
        $result = $this->matcher->match(
            $this->inspection('unknown', 'Vérification générale'),
            $this->registry('Béton', 'Le béton doit être contrôlé.')
        );

        $this->assertSame('NO_MATCH', $result['result_status']);
        $this->assertSame(0.0, (float) $result['score']);
    }

    public function test_local_classification_only_produces_a_human_review_proposal(): void
    {
        $registry = $this->registry(null, 'Le béton frais doit être contrôlé par essai slump.');
        $registry['classification_status'] = 'TO_REVIEW';
        $registry['suggested_family'] = 'Béton';

        $result = $this->matcher->match(
            $this->inspection('concrete', 'Contrôle béton frais par slump'),
            $registry
        );

        $this->assertSame('PROPOSED', $result['result_status']);
        $this->assertSame('HUMAN_REVIEW_REQUIRED', $result['review_status']);
    }

    public function test_human_classified_active_requirement_can_match_with_provenance(): void
    {
        $result = $this->matcher->match(
            $this->inspection('concrete', 'Contrôle béton frais par slump'),
            $this->registry('Béton', 'Le béton frais doit être contrôlé par essai slump.')
        );

        $this->assertSame('MATCH', $result['result_status']);
        $this->assertGreaterThanOrEqual(70, $result['score']);
        $this->assertSame(77, $result['provenance']['registry_requirement_id']);
        $this->assertNotEmpty($result['justification']);
    }

    private function inspection(string $workType, string $title): array
    {
        return [
            'id' => 10,
            'work_type' => $workType,
            'title' => $title,
            'record_type' => 'inspection',
        ];
    }

    private function registry(?string $family, string $text): array
    {
        return [
            'id' => 77,
            'publication_state' => 'ACTIVE',
            'source_atomic_statement_id' => 55,
            'reference_code' => 'NF TEST 100',
            'section_code' => '5.2',
            'heading' => 'Contrôle',
            'requirement_type' => 'REQUIREMENT',
            'requirement_text' => $text,
            'classification_family' => $family,
            'classification_status' => $family ? 'CLASSIFIED' : null,
            'source_hash' => str_repeat('a', 64),
            'provenance_hash' => str_repeat('b', 64),
        ];
    }
}
