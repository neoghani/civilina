<?php

namespace Tests\Unit;

use App\Models\NormativeLibrarianDocument;
use App\Services\Engineering\NormativeLibrarian\NormativeCanonicalConsensusService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class NormativeCanonicalConsensusTest extends TestCase
{
    public function test_validated_document_dominates_group(): void
    {
        $service = new NormativeCanonicalConsensusService();

        $docs = new Collection([
            new NormativeLibrarianDocument([
                'classification_status'=>'VALIDATED',
                'discipline'=>'Bâtiment',
                'domain'=>'CES',
                'subdomain'=>'Maçonnerie',
                'reference_code'=>'NF DTU 20.1 P1-2',
            ]),
            new NormativeLibrarianDocument([
                'classification_status'=>'TO_REVIEW',
                'discipline'=>'Bâtiment / Génie civil',
                'domain'=>'Gros œuvre',
                'subdomain'=>'Béton / béton armé',
                'reference_code'=>'NF DTU 20.1 P1-2',
            ]),
        ]);

        $r = $service->classifyGroup($docs);

        $this->assertSame('VALIDATED_PROPAGATION',$r['status']);
        $this->assertSame('CES',$r['domain']);
        $this->assertSame('Maçonnerie',$r['subdomain']);

        // score est volontairement typé float dans le service.
        $this->assertSame(100.0,$r['score']);
    }

    public function test_nf_dtu_20_1_is_strong_reference(): void
    {
        $service = new NormativeCanonicalConsensusService();

        $docs = new Collection([
            new NormativeLibrarianDocument([
                'reference_code'=>'NF DTU 20.1 P1-2',
                'classification_status'=>'UNCLASSIFIED',
            ]),
        ]);

        $r = $service->classifyGroup($docs);

        $this->assertSame('STRONG_REFERENCE',$r['status']);
        $this->assertSame('CES',$r['domain']);
        $this->assertSame('Maçonnerie',$r['subdomain']);
        $this->assertGreaterThanOrEqual(95,$r['score']);
    }

    public function test_single_weak_source_is_insufficient(): void
    {
        $service = new NormativeCanonicalConsensusService();

        $docs = new Collection([
            new NormativeLibrarianDocument([
                'reference_code'=>'ABC 123',
                'classification_status'=>'TO_REVIEW',
                'confidence'=>60,
                'discipline'=>'Bâtiment',
                'domain'=>'CES',
                'subdomain'=>'Façades / bardage',
            ]),
        ]);

        $r = $service->classifyGroup($docs);

        $this->assertSame('INSUFFICIENT',$r['status']);
    }

    public function test_two_concordant_sources_can_form_consensus(): void
    {
        $service = new NormativeCanonicalConsensusService();

        $docs = new Collection([
            new NormativeLibrarianDocument([
                'reference_code'=>'ABC 123',
                'classification_status'=>'AUTO_CLASSIFIED',
                'confidence'=>92,
                'extraction_status'=>'extracted',
                'discipline'=>'Bâtiment',
                'domain'=>'CES',
                'subdomain'=>'Façades / bardage',
            ]),
            new NormativeLibrarianDocument([
                'reference_code'=>'ABC 123',
                'classification_status'=>'AUTO_CLASSIFIED',
                'confidence'=>91,
                'extraction_status'=>'extracted',
                'discipline'=>'Bâtiment',
                'domain'=>'CES',
                'subdomain'=>'Façades / bardage',
            ]),
        ]);

        $r = $service->classifyGroup($docs);

        $this->assertSame('CONSENSUS_SAFE',$r['status']);
        $this->assertSame('CES',$r['domain']);
        $this->assertGreaterThanOrEqual(90,$r['score']);
    }
}
