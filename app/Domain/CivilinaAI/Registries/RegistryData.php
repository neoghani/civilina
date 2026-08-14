<?php

namespace App\Domain\CivilinaAI\Registries;

use RuntimeException;

final class RegistryData
{
    private static ?array $data = null;

    public static function all(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $path = resource_path('data/civilina-ai/reasoning-v2-registry.json');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read CIVILINA reasoning registry: {$path}");
        }

        return self::$data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    public static function get(string $key): array|string
    {
        return self::all()[$key] ?? throw new RuntimeException("Missing registry key: {$key}");
    }
}
