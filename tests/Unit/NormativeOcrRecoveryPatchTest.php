<?php

namespace Tests\Unit;

use App\Models\NormativeLibrarianDocument;
use App\Services\Engineering\NormativeLibrarian\NormativeReferenceRecoveryService;
use PHPUnit\Framework\TestCase;

class NormativeOcrRecoveryPatchTest extends TestCase
{
    public function test_unique_reference_from_ocr_cover_can_be_recovered(): void
    {
        $document = new NormativeLibrarianDocument();

        $document->filename = 'scan_0001.pdf';
        $document->relative_path = 'archives/scan_0001.pdf';
        $document->title = null;
        $document->subtitle = null;
        $document->cover_text = null;
        $document->ocr_text = "NF DTU 20.1 P1-2\n"
            ."Ouvrages en maçonnerie de petits éléments\n"
            ."Partie 1-2 - Critères généraux de choix des matériaux";

        $result = (new NormativeReferenceRecoveryService())->recover($document);

        $this->assertSame('RECOVERED', $result['status']);
        $this->assertSame('NF DTU 20.1 P1-2', $result['reference']);
        $this->assertGreaterThanOrEqual(80, $result['score']);
    }
}
