<?php

namespace App\Console\Commands;

use App\Services\Engineering\Normative\NormativeRegistryService;
use App\Services\Engineering\Normative\NormativeTaxonomyIndexer;
use Illuminate\Console\Command;

class CivilinaNormativeV22Refresh extends Command
{
    protected $signature = 'civilina:normative-v22:refresh {--deep : Relire aussi le contenu des fichiers} {--force : Forcer la réindexation profonde}';
    protected $description = 'Actualise catégories, domaines et index normatif Civilina V2.2.';

    public function handle(NormativeTaxonomyIndexer $taxonomy, NormativeRegistryService $registry): int
    {
        if ($this->option('deep')) {
            $this->info('Lecture/indexation profonde...');
            $stats = $registry->scan(null, (bool) $this->option('force'));
            foreach ($stats as $key => $value) $this->line(str_pad($key, 20).': '.$value);
            $this->newLine();
        }

        $stats = $taxonomy->refresh();
        $this->line('Sources classées   : '.$stats['sources']);
        $this->line('Normes actualisées : '.$stats['standards']);
        $this->info('Taxonomie normative V2.2 actualisée.');
        return self::SUCCESS;
    }
}
