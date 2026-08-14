<?php

namespace App\Domain\CivilinaAI\Registries;

final class LexiconRegistry
{
    public function entries(): array
    {
        return RegistryData::get('lexicon');
    }
}
