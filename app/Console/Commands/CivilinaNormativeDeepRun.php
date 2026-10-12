<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeDeepExtractionService;
use Illuminate\Console\Command;

class CivilinaNormativeDeepRun extends Command
{
    protected $signature = 'civilina:normative-deep:run
        {--limit=20 : Nombre de documents canoniques à analyser}
        {--all : Analyser tous les documents éligibles}';

    protected $description = 'Extrait sections et candidats exigences/tolérances sans validation automatique.';

    public function handle(NormativeDeepExtractionService $service): int
    {
        $limit = $this->option('all')
            ? null
            : max(1, (int) $this->option('limit'));

        $stats = $service->run($limit);

        $this->info('Extraction profonde terminée.');

        foreach ($stats as $key=>$value) {
            $this->line(str_pad($key, 18).' : '.$value);
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
