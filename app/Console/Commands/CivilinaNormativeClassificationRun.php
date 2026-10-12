<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeClassificationRunnerService;
use Illuminate\Console\Command;

class CivilinaNormativeClassificationRun extends Command
{
    protected $signature = 'civilina:normative-classification:run
        {--limit= : Nombre maximal de groupes normatifs canoniques}
        {--pilot : Pilote limité à cinq groupes canoniques}';

    protected $description = 'Classifie les documents normatifs locaux sans forcer les cas incertains.';

    public function handle(NormativeClassificationRunnerService $service): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $stats = $service->run($limit, (bool) $this->option('pilot'));

        foreach ($stats as $key => $value) {
            $this->line($key.' = '.$value);
        }

        return self::SUCCESS;
    }
}
