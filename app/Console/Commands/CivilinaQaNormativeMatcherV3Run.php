<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeQaRegistryMatchingService;
use Illuminate\Console\Command;

class CivilinaQaNormativeMatcherV3Run extends Command
{
    protected $signature = 'civilina:qa-normative-v3:match
        {--limit=5 : Nombre maximal d inspections}
        {--pilot : Pilote limité à cinq inspections}
        {--inspection= : Identifiant d une inspection précise}';

    protected $description = 'Match QA/QC V3 uniquement contre le registre normatif ACTIVE.';

    public function handle(NormativeQaRegistryMatchingService $service): int
    {
        $stats = $service->run(
            max(1, (int) $this->option('limit')),
            (bool) $this->option('pilot'),
            $this->option('inspection') !== null ? (int) $this->option('inspection') : null
        );

        foreach ($stats as $key => $value) {
            $this->line($key.' = '.$value);
        }

        return self::SUCCESS;
    }
}
