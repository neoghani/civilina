<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeCanonicalConsensusService;
use Illuminate\Console\Command;

class CivilinaNormativeLibrarianConsensus extends Command
{
    protected $signature = 'civilina:normative-librarian:consensus
        {--limit-groups= : Limite facultative de groupes canoniques}';

    protected $description = 'Consolide les classifications par référence canonique et édition.';

    public function handle(NormativeCanonicalConsensusService $service): int
    {
        $limit = $this->option('limit-groups');

        $stats = $service->run(
            $limit !== null ? max(1, (int)$limit) : null
        );

        $this->info('Consensus canonique terminé.');

        foreach ($stats as $key=>$value) {
            $this->line(str_pad($key, 30).' : '.$value);
        }

        return self::SUCCESS;
    }
}
