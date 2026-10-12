<?php

namespace Tests\Unit;

use App\Models\NormativeLibrarianDocument;
use App\Services\Engineering\NormativeLibrarian\NormativeCanonicalizer;
use PHPUnit\Framework\TestCase;

class NormativeCanonicalizerTest extends TestCase
{
    public function test_reference_is_normalized(): void
    {
        $service = new NormativeCanonicalizer();

        $document = new NormativeLibrarianDocument([
            'reference_code'=>'NF DTU 20.1 P1-2',
        ]);

        $this->assertSame(
            'NFDTU201P12',
            $service->normalizedReference($document)
        );
    }

    public function test_different_year_is_different_edition(): void
    {
        $service = new NormativeCanonicalizer();

        $a = new NormativeLibrarianDocument(['publication_year'=>2008]);
        $b = new NormativeLibrarianDocument(['publication_year'=>2020]);

        $this->assertNotSame(
            $service->editionKey($a,'NFDTU201P12'),
            $service->editionKey($b,'NFDTU201P12')
        );
    }

    public function test_unknown_year_is_explicit(): void
    {
        $service = new NormativeCanonicalizer();

        $document = new NormativeLibrarianDocument([]);

        $this->assertSame(
            'EN206__ANNEE_INCONNUE',
            $service->editionKey($document,'EN206')
        );
    }
}
