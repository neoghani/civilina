<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeAtomicStatementReviewService;
use Illuminate\Console\Command;

class CivilinaNormativeAtomicReview extends Command
{
    protected $signature = 'civilina:normative-atomic:review
        {statement : ID du statement atomique}
        {decision : CANDIDATE | VALIDATED | REJECTED}
        {--note= : Note de validation ou motif obligatoire du rejet}
        {--actor=CLI_REVIEWER : Nom du relecteur}';

    protected $description = 'Enregistre une decision humaine historisee sur un statement atomique.';

    public function handle(NormativeAtomicStatementReviewService $service): int
    {
        try {
            $service->decide(
                (int) $this->argument('statement'),
                (string) $this->argument('decision'),
                (string) $this->option('note'),
                null,
                trim((string) $this->option('actor')) ?: 'CLI_REVIEWER'
            );
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Decision atomique enregistree et historisee.');

        return self::SUCCESS;
    }
}
