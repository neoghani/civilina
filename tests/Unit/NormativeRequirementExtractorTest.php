<?php

namespace Tests\Unit;

use App\Services\Engineering\Normative\NormativeRequirementExtractor;
use PHPUnit\Framework\TestCase;

class NormativeRequirementExtractorTest extends TestCase
{
    public function test_extracts_tolerance_without_inventing_values(): void
    {
        $service = new NormativeRequirementExtractor();

        $items = $service->extract('La dimension doit être de 100 ± 5 mm. Le présent paragraphe est informatif.');

        $this->assertCount(1, $items);
        $this->assertSame('tolerance', $items[0]['requirement_type']);
        $this->assertSame(100.0, $items[0]['nominal_value']);
        $this->assertSame(5.0, $items[0]['tolerance_plus']);
        $this->assertSame('mm', $items[0]['unit']);
        $this->assertStringContainsString('100 ± 5 mm', $items[0]['requirement_text']);
    }

    public function test_does_not_create_requirement_from_informative_text(): void
    {
        $service = new NormativeRequirementExtractor();

        $items = $service->extract('Ce document présente les principes généraux de la méthode.');

        $this->assertCount(0, $items);
    }
}
