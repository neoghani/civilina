<?php

namespace App\Console\Commands;

use App\Services\Engineering\Normative\NormativeRegistryService;
use Illuminate\Console\Command;

class CivilinaNormativeV2Scan extends Command
{
    protected $signature = 'civilina:normative-v2:scan
        {--all : Scanner tous les fichiers}
        {--limit=50 : Nombre maximum de fichiers}
        {--force : Réindexer même si le fichier est inchangé}';

    protected $description = 'Lit les normes, déduplique les sources, enregistre les références, sections et exigences/tolérances.';

    public function handle(NormativeRegistryService $registry): int
    {
        $limit = $this->option('all') ? null : max(1, (int) $this->option('limit'));

        $this->info('CIVILINA Agent normatif V2 — indexation en cours...');
        $stats = $registry->scan($limit, (bool) $this->option('force'));

        foreach ($stats as $key => $value) {
            $this->line(str_pad($key, 18).': '.$value);
        }

        $this->newLine();
        $this->info('Indexation terminée.');
        return self::SUCCESS;
    }
}
