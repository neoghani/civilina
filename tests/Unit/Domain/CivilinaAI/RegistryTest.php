<?php

namespace Tests\Unit\Domain\CivilinaAI;

use App\Domain\CivilinaAI\Registries\CapabilityRegistry;
use App\Domain\CivilinaAI\Registries\DomainRegistry;
use Tests\TestCase;

class RegistryTest extends TestCase
{
    public function test_v2_registries_keep_domains_and_capabilities_separate(): void
    {
        $domains = $this->app->make(DomainRegistry::class)->ids();
        $capabilities = $this->app->make(CapabilityRegistry::class)->ids();

        $this->assertSame(['executiveControl', 'projects', 'organisation', 'managementSystems', 'digitalisation', 'people'], $domains);
        $this->assertCount(17, $capabilities);
        $this->assertSame([], array_values(array_intersect($domains, $capabilities)));
    }
}
