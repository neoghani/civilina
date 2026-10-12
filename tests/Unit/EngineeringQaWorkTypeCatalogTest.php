<?php

namespace Tests\Unit;

use App\Services\Engineering\EngineeringQaWorkTypeCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EngineeringQaWorkTypeCatalogTest extends TestCase
{
    #[Test]
    public function concrete_contains_pre_pour_fresh_and_7_28_day_records(): void
    {
        $catalog = app(EngineeringQaWorkTypeCatalog::class);
        $templates = collect($catalog->templates(EngineeringQaWorkTypeCatalog::REINFORCED_CONCRETE));

        $this->assertTrue($templates->contains(fn (array $t) => $t['key'] === 'RC-PREPOUR'));
        $this->assertTrue($templates->contains(fn (array $t) => $t['key'] === 'RC-FRESH'));
        $this->assertTrue($templates->contains(fn (array $t) => $t['key'] === 'RC-LAB-7D'));
        $this->assertTrue($templates->contains(fn (array $t) => $t['key'] === 'RC-LAB-28D'));
    }

    #[Test]
    public function steel_contains_quantitative_receipt_erection_and_torque_registers(): void
    {
        $catalog = app(EngineeringQaWorkTypeCatalog::class);
        $templates = collect($catalog->templates(EngineeringQaWorkTypeCatalog::STRUCTURAL_STEEL));

        $this->assertTrue($templates->contains(fn (array $t) => $t['register_type'] === 'steel_receipt'));
        $this->assertTrue($templates->contains(fn (array $t) => $t['register_type'] === 'steel_erection'));
        $this->assertTrue($templates->contains(fn (array $t) => $t['register_type'] === 'bolt_torque'));
    }

    #[Test]
    public function mixed_structure_combines_both_domains_and_requires_handover(): void
    {
        $catalog = app(EngineeringQaWorkTypeCatalog::class);

        $templates = $catalog->templates(EngineeringQaWorkTypeCatalog::MIXED_STRUCTURE);
        $handovers = $catalog->handoverTemplates(EngineeringQaWorkTypeCatalog::MIXED_STRUCTURE);

        $this->assertGreaterThanOrEqual(11, count($templates));
        $this->assertCount(1, $handovers);
        $this->assertSame(EngineeringQaWorkTypeCatalog::REINFORCED_CONCRETE, $handovers[0]['from_work_type']);
        $this->assertSame(EngineeringQaWorkTypeCatalog::STRUCTURAL_STEEL, $handovers[0]['to_work_type']);
    }

    #[Test]
    public function earthworks_has_troxler_plate_lab_and_deflection_tracking(): void
    {
        $catalog = app(EngineeringQaWorkTypeCatalog::class);
        $templates = collect($catalog->templates(EngineeringQaWorkTypeCatalog::EARTHWORKS_ROAD));

        $registerTypes = $templates->pluck('register_type')->filter()->all();
        $this->assertContains('troxler_density', $registerTypes);
        $this->assertContains('plate_load', $registerTypes);
        $this->assertContains('soil_lab', $registerTypes);
        $this->assertContains('deflection', $registerTypes);
    }

    #[Test]
    public function catalog_does_not_hard_code_compaction_or_bearing_acceptance_values(): void
    {
        $catalog = app(EngineeringQaWorkTypeCatalog::class);
        $serialized = json_encode([
            $catalog->templates(EngineeringQaWorkTypeCatalog::EARTHWORKS_ROAD),
            $catalog->registerSchemas(),
        ], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('95%', $serialized);
        $this->assertStringNotContainsString('98%', $serialized);
        $this->assertStringContainsString('requirement_source', $serialized);
    }
}
