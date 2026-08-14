<?php

namespace App\Domain\CivilinaAI\Registries;

final class BusinessEntityRegistry
{
    public function ids(): array
    {
        return RegistryData::get('businessEntities');
    }
}
