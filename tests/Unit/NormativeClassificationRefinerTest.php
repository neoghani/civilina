<?php

namespace Tests\Unit;

use App\Models\NormativeLibrarianDocument;
use App\Services\Engineering\NormativeLibrarian\NormativeClassificationRefiner;
use PHPUnit\Framework\TestCase;

class NormativeClassificationRefinerTest extends TestCase
{
    private function classify(array $attrs): array
    {
        $document = new NormativeLibrarianDocument($attrs);

        return (new NormativeClassificationRefiner())->classify($document);
    }

    public function test_classifies_masonry_dtu(): void
    {
        $r = $this->classify([
            'reference_code' => 'NF DTU 20.1 P1-2',
            'title' => 'Ouvrages en maçonnerie de petits éléments — Parois et murs',
        ]);

        $this->assertSame('Bâtiment', $r['discipline']);
        $this->assertSame('CES', $r['domain']);
        $this->assertSame('Maçonnerie', $r['subdomain']);
    }

    public function test_classifies_concrete(): void
    {
        $r = $this->classify([
            'reference_code' => 'NF EN 206',
            'title' => 'Béton',
        ]);

        $this->assertSame('Gros œuvre', $r['domain']);
        $this->assertSame('Béton / béton armé', $r['subdomain']);
    }

    public function test_classifies_steel(): void
    {
        $r = $this->classify([
            'reference_code' => 'EN 1090-2',
            'title' => 'Exécution des structures en acier',
        ]);

        $this->assertSame('Structure métallique', $r['domain']);
        $this->assertSame('Charpente métallique', $r['subdomain']);
    }

    public function test_classifies_welding(): void
    {
        $r = $this->classify([
            'reference_code' => 'ISO 9606-1',
            'title' => 'Qualification des soudeurs',
        ]);

        $this->assertSame('Structure métallique', $r['domain']);
        $this->assertSame('Soudage', $r['subdomain']);
    }

    public function test_classifies_gtr(): void
    {
        $r = $this->classify([
            'reference_code' => 'GTR',
            'title' => 'Guide des terrassements routiers',
        ]);

        $this->assertSame('Routes & terrassements', $r['domain']);
        $this->assertSame('Terrassements / compactage', $r['subdomain']);
    }
}
