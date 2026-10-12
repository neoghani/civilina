<?php

namespace App\Services\Engineering\NormativeLibrarian;

class NormativeAtomicValueMapper
{
    public function first(mixed $entities): array
    {
        $decoded = is_string($entities) ? json_decode($entities, true) : $entities;
        $first = is_array($decoded) && isset($decoded[0]) && is_array($decoded[0])
            ? $decoded[0]
            : [];

        return [
            'operator' => $first['operator'] ?? null,
            'value' => $first['value'] ?? null,
            'unit' => $first['unit'] ?? null,
        ];
    }
}
