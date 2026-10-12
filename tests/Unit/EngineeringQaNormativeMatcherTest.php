<?php

namespace Tests\Unit;

use App\Services\Engineering\EngineeringQaNormativeMatcher;
use PHPUnit\Framework\TestCase;

class EngineeringQaNormativeMatcherTest extends TestCase
{
    public function test_rebar_inspection_prioritizes_reinforcement_terms(): void
    {
        $matcher = new EngineeringQaNormativeMatcher();
        $keywords = $matcher->keywords('reinforced_concrete', 'PV inspection ferraillage poteaux');

        $this->assertContains('armature', $keywords);
        $this->assertContains('enrobage', $keywords);
    }

    public function test_fresh_concrete_matches_consistency_clause(): void
    {
        $matcher = new EngineeringQaNormativeMatcher();
        $keywords = $matcher->keywords('reinforced_concrete', 'PV béton frais');

        $result = $matcher->score(
            'Fresh concrete consistency shall be determined by slump test and sampling requirements.',
            $keywords
        );

        $this->assertGreaterThan(20, $result['score']);
        $this->assertNotEmpty($result['matched']);
    }

    public function test_steel_tightening_matches_torque_clause(): void
    {
        $matcher = new EngineeringQaNormativeMatcher();
        $keywords = $matcher->keywords('steel_structure', 'Réception à la clé dynamométrique - serrage boulons');

        $result = $matcher->score(
            'Bolted assemblies shall be tightened using the specified torque and calibrated tightening equipment.',
            $keywords
        );

        $this->assertGreaterThan(20, $result['score']);
    }

    public function test_earthworks_matches_compaction_clause(): void
    {
        $matcher = new EngineeringQaNormativeMatcher();
        $keywords = $matcher->keywords('earthworks', 'Contrôle Troxler remblai CDF');

        $result = $matcher->score(
            'Field density and moisture content are used to assess soil compaction for fill layers.',
            $keywords
        );

        $this->assertGreaterThan(15, $result['score']);
    }

    public function test_agent_does_not_embed_acceptance_values(): void
    {
        $matcher = new EngineeringQaNormativeMatcher();
        $keywords = $matcher->keywords('road', 'Essai de plaque et compacité');

        $joined = implode(' ', $keywords);

        $this->assertStringNotContainsString('95%', $joined);
        $this->assertStringNotContainsString('98%', $joined);
        $this->assertStringNotContainsString('35 MPa', $joined);
    }
}
