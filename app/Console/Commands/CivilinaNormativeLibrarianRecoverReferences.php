<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeReferenceRecoveryService;
use Illuminate\Console\Command;

class CivilinaNormativeLibrarianRecoverReferences extends Command
{
    protected $signature = 'civilina:normative-librarian:recover-references
        {--limit= : Limite facultative}';

    protected $description = 'Récupère prudemment les références normatives manquantes.';

    public function handle(NormativeReferenceRecoveryService $service): int
    {
        $limit = $this->option('limit');

        $stats = $service->run(
            $limit !== null ? max(1,(int)$limit) : null
        );

        $this->info('Récupération des références terminée.');

        foreach ($stats as $key=>$value) {
            $this->line(str_pad($key, 28).' : '.$value);
        }

        return self::SUCCESS;
    }
}
