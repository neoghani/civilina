<?php

namespace Tests\Unit;

use App\Services\Engineering\Normative\NormativeAgentPromptBuilder;
use App\Services\Engineering\Normative\NormativeQaMatcher;
use PHPUnit\Framework\TestCase;

class NormativeQaMatcherSemanticTest extends TestCase
{
    private function matcher(): NormativeQaMatcher
    {
        return new NormativeQaMatcher(new NormativeAgentPromptBuilder());
    }

    public function test_rejects_concrete_foundation_sentence_for_earthworks_control(): void
    {
        $matcher = $this->matcher();

        $this->assertFalse($matcher->candidateIsRelevant(
            'PV réception sol naturel / fond de terrassement earthworks_road',
            "Les mesures sur un élément de fondation ne peuvent avoir lieu qu'au minimum sept jours après la fin du bétonnage.",
            'earthworks_road',
            null
        ));
    }

    public function test_accepts_compaction_requirement_for_troxler(): void
    {
        $matcher = $this->matcher();

        $this->assertTrue($matcher->candidateIsRelevant(
            'PV essai Troxler densité compacité couche de remblai earthworks_road',
            'Le contrôle de compacité de la couche doit être effectué par mesure de densité.',
            'earthworks_road',
            'compacité'
        ));
    }

    public function test_accepts_plate_bearing_requirement_for_portance_control(): void
    {
        $matcher = $this->matcher();

        $this->assertTrue($matcher->candidateIsRelevant(
            'Essai de plaque portance EV2 plateforme',
            'Le module EV2 de la plateforme doit satisfaire le critère de portance défini.',
            'earthworks_road',
            'portance'
        ));
    }

    public function test_accepts_rebar_cover_requirement_for_reinforcement_control(): void
    {
        $matcher = $this->matcher();

        $this->assertTrue($matcher->candidateIsRelevant(
            'PV inspection ferraillage poteaux béton armé',
            "L'enrobage des armatures doit respecter la valeur spécifiée.",
            'concrete',
            'enrobage'
        ));
    }
}
