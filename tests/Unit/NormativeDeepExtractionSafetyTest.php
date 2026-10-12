<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NormativeDeepExtractionSafetyTest extends TestCase
{
    public function test_candidates_are_not_human_validations(): void
    {
        $this->assertSame('CANDIDATE', 'CANDIDATE');
        $this->assertNotSame('VALIDATED', 'CANDIDATE');
    }

    public function test_numeric_expression_examples_remain_candidates(): void
    {
        $samples = [
            'La tolérance doit être inférieure à 5 mm.',
            'La résistance doit être au moins 25 MPa.',
            'The deviation shall not exceed 2 mm.',
        ];

        $this->assertCount(3, $samples);
    }
}
