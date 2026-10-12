<?php

namespace Tests\Unit;

use App\Models\NormativeLibrarianDocument;
use App\Services\Engineering\NormativeLibrarian\NormativeSafeClassifier;
use PHPUnit\Framework\TestCase;

class NormativeSafeClassifierTest extends TestCase
{
    private function classify(array $attrs): array
    {
        return (new NormativeSafeClassifier())->classify(
            new NormativeLibrarianDocument($attrs)
        );
    }

    public function test_nf_dtu_20_1_is_high_confidence_masonry(): void
    {
        $r = $this->classify([
            'reference_code'=>'NF DTU 20.1 P1-2',
            'title'=>'Ouvrages en maçonnerie de petits éléments — Parois et murs',
        ]);

        $this->assertSame('CES', $r['domain']);
        $this->assertSame('Maçonnerie', $r['subdomain']);
        $this->assertGreaterThanOrEqual(80, $r['score']);
    }

    public function test_en_1090_with_structure_title_is_high_confidence_steel(): void
    {
        $r = $this->classify([
            'reference_code'=>'EN 1090-2',
            'title'=>'Exécution des structures en acier',
        ]);

        $this->assertSame('Structure métallique', $r['domain']);
        $this->assertSame('Charpente métallique', $r['subdomain']);
        $this->assertGreaterThanOrEqual(80, $r['score']);
    }

    public function test_generic_steel_word_alone_is_not_auto_classification(): void
    {
        $r = $this->classify([
            'title'=>'Caractéristiques mécaniques de produits en acier',
        ]);

        $this->assertLessThan(80, $r['score']);
    }

    public function test_generic_concrete_word_alone_is_not_auto_classification(): void
    {
        $r = $this->classify([
            'title'=>'Produit compatible avec le béton',
        ]);

        $this->assertLessThan(80, $r['score']);
    }

    public function test_iso_9606_is_high_confidence_welding(): void
    {
        $r = $this->classify([
            'reference_code'=>'ISO 9606-1',
            'title'=>'Qualification des soudeurs',
        ]);

        $this->assertSame('Soudage', $r['subdomain']);
        $this->assertGreaterThanOrEqual(80, $r['score']);
    }

    public function test_gtr_with_terrassement_phrase_is_high_confidence(): void
    {
        $r = $this->classify([
            'title'=>'Guide des terrassements routiers GTR',
        ]);

        $this->assertSame('Routes & terrassements', $r['domain']);
        $this->assertGreaterThanOrEqual(80, $r['score']);
    }
}
