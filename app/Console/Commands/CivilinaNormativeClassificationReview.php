<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeClassificationReviewService;
use Illuminate\Console\Command;

class CivilinaNormativeClassificationReview extends Command
{
    protected $signature = 'civilina:normative-classification:review
        {classification : ID de la classification TO_REVIEW}
        {decision : CLASSIFIED ou UNCLASSIFIED}
        {--note= : Justification humaine obligatoire}
        {--actor= : Nom du relecteur humain obligatoire}';

    protected $description = 'Enregistre une décision humaine sur une classification normative proposée.';

    public function handle(NormativeClassificationReviewService $service): int
    {
        try {
            $service->decide(
                (int) $this->argument('classification'),
                (string) $this->argument('decision'),
                (string) $this->option('note'),
                (string) $this->option('actor')
            );
        } catch (\DomainException|\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Décision humaine de classification enregistrée.');

        return self::SUCCESS;
    }
}
