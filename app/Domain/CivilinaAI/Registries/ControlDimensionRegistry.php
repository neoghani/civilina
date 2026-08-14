<?php

namespace App\Domain\CivilinaAI\Registries;

final class ControlDimensionRegistry
{
    public function ids(): array
    {
        return RegistryData::get('controlDimensions');
    }
}
