<?php

namespace Tests\Unit;

use App\Models\EngineeringItp;
use App\Models\EngineeringItpRow;
use App\Services\Engineering\EngineeringItpReadinessService;
use PHPUnit\Framework\TestCase;

class EngineeringItpDomainTest extends TestCase
{
    public function test_itp_without_rows_is_not_ready(): void
    {
        $itp = $this->baseItp();
        $itp->setRelation('rows', collect());

        $result = (new EngineeringItpReadinessService())->evaluate($itp);

        $this->assertFalse($result['ready']);
        $this->assertContains('Au moins une ligne de contrôle / essai est requise.', $result['blockers']);
    }

    public function test_itp_row_without_acceptance_criterion_is_not_ready(): void
    {
        $itp = $this->baseItp();
        $itp->setRelation('rows', collect([$this->completeRow(['acceptance_criteria' => null])]));

        $result = (new EngineeringItpReadinessService())->evaluate($itp);

        $this->assertFalse($result['ready']);
        $this->assertStringContainsString('critère d’acceptation manquant', implode(' | ', $result['blockers']));
    }

    public function test_complete_itp_is_ready_for_review(): void
    {
        $itp = $this->baseItp();
        $itp->setRelation('rows', collect([$this->completeRow()]));

        $result = (new EngineeringItpReadinessService())->evaluate($itp);

        $this->assertTrue($result['ready']);
        $this->assertSame([], $result['blockers']);
        $this->assertSame(1, $result['row_count']);
    }

    private function baseItp(): EngineeringItp
    {
        return new EngineeringItp([
            'code' => 'ITP-STR-001',
            'title' => 'ITP structure béton',
            'scope_type' => 'activity',
            'scope_ref' => 'ACT-STR-001',
            'scope_label' => 'Ferraillage poteaux',
            'reference_documents' => ['Plan STR-001', 'EN 206'],
            'status' => EngineeringItp::STATUS_DRAFT,
        ]);
    }

    private function completeRow(array $overrides = []): EngineeringItpRow
    {
        return new EngineeringItpRow(array_merge([
            'sequence' => 10,
            'code' => 'ITP-STR-001-010',
            'inspection_test' => 'Contrôle du ferraillage',
            'method' => 'Inspection visuelle et mesure',
            'frequency' => 'Chaque poteau',
            'acceptance_criteria' => 'Conforme au plan approuvé',
            'checkpoint_type' => EngineeringItpRow::CHECKPOINT_HOLD,
            'responsibility_execute' => 'Entreprise',
            'responsibility_inspect' => 'QA/QC',
            'responsibility_approve' => 'Responsable qualité',
            'responsibility_accept' => 'Client',
            'record_required' => 'Fiche de contrôle',
            'source_reference' => 'Plan STR-001',
        ], $overrides));
    }
}
