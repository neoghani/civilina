<?php

namespace Tests\Unit\Domain\CivilinaAI;

use App\Domain\CivilinaAI\Application\RunDiagnostic;
use Tests\TestCase;

class ReasoningParityTest extends TestCase
{
    public function test_php_reasoning_matches_every_golden_v2_contract(): void
    {
        $dataset = json_decode(file_get_contents(base_path('tests/Fixtures/golden-reasoning-v2.json')), true, 512, JSON_THROW_ON_ERROR);
        $diagnostic = $this->app->make(RunDiagnostic::class);

        $this->assertGreaterThanOrEqual(150, count($dataset['cases']));
        foreach ($dataset['cases'] as $case) {
            $this->assertSame($case['expected'], $diagnostic->execute($case['input'])->jsonSerialize(), $case['id']);
        }
    }
}
