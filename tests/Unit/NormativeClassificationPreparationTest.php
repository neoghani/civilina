<?php

namespace Tests\Unit;

use App\Services\Engineering\NormativeLibrarian\NormativeClassificationPreparationService;
use PHPUnit\Framework\TestCase;

class NormativeClassificationPreparationTest extends TestCase
{
    public function test_no_local_evidence_stays_unclassified(): void
    {
        $result = (new NormativeClassificationPreparationService())->prepare(['title' => 'Document sans contexte']);

        $this->assertSame('UNCLASSIFIED', $result['status']);
        $this->assertNull($result['family']);
    }

    public function test_detected_signals_only_create_a_human_review_suggestion(): void
    {
        $result = (new NormativeClassificationPreparationService())->prepare([
            'title' => 'Controle qualite du beton',
            'path' => 'ouvrages/beton/controle.pdf',
        ]);

        $this->assertSame('TO_REVIEW', $result['status']);
        $this->assertSame('Béton', $result['suggested_family']);
        $this->assertNull($result['family']);
    }

    public function test_existing_local_classification_is_preserved(): void
    {
        $result = (new NormativeClassificationPreparationService())->prepare([
            'existing_classification' => 'Qualité',
        ]);

        $this->assertSame('CLASSIFIED', $result['status']);
        $this->assertSame('Qualité', $result['family']);
    }
}
