<?php

namespace Tests\Unit;

use App\Models\NormativeLibrarianDocument;
use App\Services\Engineering\NormativeLibrarian\NormativeReferenceRecoveryService;
use PHPUnit\Framework\TestCase;

class NormativeReferenceRecoveryServiceTest extends TestCase
{
    public function test_filename_and_title_can_recover_reference(): void
    {
        $service = new NormativeReferenceRecoveryService();

        $document = new NormativeLibrarianDocument([
            'filename'=>'NF DTU 20.1 P1-2 - maçonnerie.pdf',
            'title'=>'NF DTU 20.1 P1-2 Ouvrages en maçonnerie',
        ]);

        $r = $service->recover($document);

        $this->assertSame('RECOVERED',$r['status']);
        $this->assertSame('NF DTU 20.1 P1-2',$r['reference']);
        $this->assertGreaterThanOrEqual(80,$r['score']);
    }

    public function test_nested_dtu_alias_is_collapsed(): void
    {
        $service = new NormativeReferenceRecoveryService();

        $refs = $service->extractReferences(
            'NF DTU 20.1 P1-2 Ouvrages en maçonnerie'
        );

        $this->assertSame(['NF DTU 20.1 P1-2'],$refs);
    }

    public function test_nested_nf_en_alias_is_collapsed(): void
    {
        $service = new NormativeReferenceRecoveryService();

        $refs = $service->extractReferences(
            'Norme NF EN 1090-2 Exécution des structures en acier'
        );

        $this->assertSame(['NF EN 1090-2'],$refs);
    }

    public function test_nested_nf_en_iso_alias_is_collapsed(): void
    {
        $service = new NormativeReferenceRecoveryService();

        $refs = $service->extractReferences(
            'NF EN ISO 9606-1 Qualification des soudeurs'
        );

        $this->assertSame(['NF EN ISO 9606-1'],$refs);
    }

    public function test_single_filename_signal_is_review_only(): void
    {
        $service = new NormativeReferenceRecoveryService();

        $document = new NormativeLibrarianDocument([
            'filename'=>'EN 1090-2.pdf',
        ]);

        $r = $service->recover($document);

        $this->assertSame('TO_REVIEW',$r['status']);
        $this->assertSame('EN 1090-2',$r['reference']);
        $this->assertLessThan(80,$r['score']);
    }

    public function test_real_two_reference_case_remains_ambiguous(): void
    {
        $service = new NormativeReferenceRecoveryService();

        $document = new NormativeLibrarianDocument([
            'filename'=>'EN 206 - EN 12390.pdf',
            'title'=>'EN 206 / EN 12390',
        ]);

        $r = $service->recover($document);

        $this->assertSame('AMBIGUOUS',$r['status']);
        $this->assertNull($r['reference']);
    }

    public function test_extracts_iso_reference(): void
    {
        $service = new NormativeReferenceRecoveryService();

        $refs = $service->extractReferences(
            'Qualification des soudeurs selon ISO 9606-1'
        );

        $this->assertContains('ISO 9606-1',$refs);
    }
}
