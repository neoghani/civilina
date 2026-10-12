<?php

namespace App\Console\Commands;

use App\Services\Engineering\EngineeringStandardInboxService;
use Illuminate\Console\Command;

class CivilinaScanStandards extends Command
{
    protected $signature = 'civilina:standards-scan
                            {--batch=50 : Nombre de fichiers par lot}
                            {--all : Continuer jusqu’à la fin du dossier}';

    protected $description = 'Scanne le dossier privé des normes Civilina sans timeout HTTP.';

    public function handle(EngineeringStandardInboxService $service): int
    {
        $batch = max(1, min((int) $this->option('batch'), 100));
        $scanAll = (bool) $this->option('all');

        if ($scanAll && function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        do {
            $result = $service->scan($batch);

            $this->line(sprintf(
                'Total: %d | Traités: %d | Déjà à jour: %d | Restants: %d | Erreurs: %d',
                $result['total_files'],
                $result['processed'],
                $result['already_current'],
                $result['remaining'],
                $result['errors']
            ));

            if (! $scanAll) {
                break;
            }

            if ($result['processed'] === 0 && $result['remaining'] > 0) {
                $this->error('Aucun progrès possible sur le lot. Arrêt de sécurité.');
                return self::FAILURE;
            }
        } while (! $result['complete']);

        $this->info($result['complete']
            ? 'Scan des normes terminé.'
            : 'Lot terminé. Relance la commande ou utilise --all pour poursuivre.');

        return self::SUCCESS;
    }
}
