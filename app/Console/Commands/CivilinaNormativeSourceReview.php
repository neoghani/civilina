<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeSourceReviewService;
use Illuminate\Console\Command;

class CivilinaNormativeSourceReview extends Command
{
    protected $signature = 'civilina:normative-source:review
        {document : ID du document Deep}
        {decision : PRIMARY_VALIDATED | SECONDARY_REJECTED | NEEDS_REVIEW}
        {--note= : Motif humain obligatoire}
        {--actor=CLI_REVIEWER : Nom du relecteur}';

    protected $description = 'Enregistre une decision humaine historisee sur une source normative.';

    public function handle(NormativeSourceReviewService $service): int
    {
        try {
            $service->decide(
                (int) $this->argument('document'),
                (string) $this->argument('decision'),
                (string) $this->option('note'),
                null,
                trim((string) $this->option('actor')) ?: 'CLI_REVIEWER'
            );
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Decision de source enregistree et historisee.');

        return self::SUCCESS;
    }
}
