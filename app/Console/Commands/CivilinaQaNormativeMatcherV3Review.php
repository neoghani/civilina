<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeQaRegistryMatchReviewService;
use Illuminate\Console\Command;

class CivilinaQaNormativeMatcherV3Review extends Command
{
    protected $signature = 'civilina:qa-normative-v3:review
        {match : Identifiant du résultat PROPOSED}
        {decision : VALIDATED ou REJECTED}
        {--note= : Justification humaine obligatoire}
        {--actor= : Nom du décideur humain obligatoire}';

    protected $description = 'Enregistre la décision humaine sur une proposition QA/QC V3.';

    public function handle(NormativeQaRegistryMatchReviewService $service): int
    {
        $service->review(
            (int) $this->argument('match'),
            (string) $this->argument('decision'),
            (string) $this->option('note'),
            (string) $this->option('actor')
        );

        $this->info('Décision humaine V3 enregistrée sans modifier le statut métier PROPOSED.');

        return self::SUCCESS;
    }
}
