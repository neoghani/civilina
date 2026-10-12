<?php

namespace Tests\Unit;

use App\Services\Engineering\EngineeringStandardReferenceMatcher;
use PHPUnit\Framework\TestCase;

class EngineeringStandardReferenceMatcherTest extends TestCase
{
    public function test_it_detects_nf_en_reference_from_filename(): void
    {
        $matcher = new EngineeringStandardReferenceMatcher();

        $reference = $matcher->detectReference('NF EN 206-1 beton specification');

        $this->assertNotNull($reference);
        $this->assertStringContainsString('NF EN', $reference);
    }

    public function test_exact_reference_has_priority(): void
    {
        $matcher = new EngineeringStandardReferenceMatcher();

        $catalog = [
            ['id' => '1', 'label' => 'EN 206', 'values' => ['EN 206', 'Béton - Spécification']],
            ['id' => '2', 'label' => 'EN 1090-2', 'values' => ['EN 1090-2', 'Exécution des structures en acier']],
        ];

        $match = $matcher->match('EN 1090-2 execution structures acier', 'EN 1090-2', $catalog);

        $this->assertNotNull($match);
        $this->assertSame('2', $match['id']);
        $this->assertGreaterThanOrEqual(88, $match['score']);
    }

    public function test_weak_filename_does_not_force_false_match(): void
    {
        $matcher = new EngineeringStandardReferenceMatcher();

        $catalog = [
            ['id' => '1', 'label' => 'EN 206', 'values' => ['EN 206', 'Béton - Spécification']],
        ];

        $match = $matcher->match('2130 24 aide au choix des granulats pour', null, $catalog);

        $this->assertNull($match);
    }
}
