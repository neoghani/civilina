<?php

namespace App\Domain\CivilinaAI\Registries;

final class CapabilityRegistry
{
    public function all(): array
    {
        return RegistryData::get('capabilities');
    }

    public function ids(): array
    {
        return array_keys($this->all());
    }
}
