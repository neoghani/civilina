<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeControlledPublicationService;
use Illuminate\Console\Command;

class CivilinaNormativePublishValidated extends Command
{
    protected $signature = 'civilina:normative-deep:publish
        {--limit=10 : Nombre maximal de statements VALIDATED a publier}
        {--all : Selectionner tous les statements eligibles}
        {--statement= : Selectionner un statement atomique precis}
        {--pilot : Limiter la publication pilote a 10 statements}
        {--actor=SYSTEM_CONTROLLED : Identite technique de publication}
        {--dry-run : Simuler sans ecrire dans le registre}';

    protected $description = 'Publie uniquement les exigences/tolérances VALIDATED vers le registre normatif Civilina.';

    public function handle(NormativeControlledPublicationService $service): int
    {
        $statement = $this->option('statement');
        $statementId = $statement !== null ? (int) $statement : null;

        $limit = $this->option('all')
            ? null
            : max(1, (int) $this->option('limit'));

        if ($statementId !== null) {
            $limit = 1;
        }

        $stats = $service->publish(
            $limit,
            (bool) $this->option('dry-run'),
            $statementId,
            (bool) $this->option('pilot'),
            trim((string) $this->option('actor')) ?: 'SYSTEM_CONTROLLED'
        );

        $this->info(
            $this->option('dry-run')
                ? 'Simulation de publication terminée.'
                : 'Publication contrôlée terminée.'
        );

        foreach ($stats as $key=>$value) {
            $this->line(str_pad($key, 18).' : '.$value);
        }

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
