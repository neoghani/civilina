<?php

namespace App\Domain\CivilinaAI\Registries;

final class DomainRegistry
{
    public function all(): array
    {
        return RegistryData::get('businessDomains');
    }

    public function ids(): array
    {
        return array_keys($this->all());
    }
}
