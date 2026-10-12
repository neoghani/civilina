<?php

namespace App\Console\Commands;

use App\Services\Engineering\Normative\NormativeLibraryTaxonomyService;
use Illuminate\Console\Command;

class CivilinaNormativeLibraryReclassify extends Command
{
    protected $signature = 'civilina:normative-library:reclassify';
    protected $description = 'Reclasse la bibliothèque normative Civilina.';

    public function handle(NormativeLibraryTaxonomyService $service): int
    {
        $stats = $service->reclassifyAll();

        $this->info('Bibliothèque normative reclassée.');
        $this->line('Total    : '.$stats['total']);
        $this->line('Classées : '.$stats['classified']);
        $this->line('À revoir : '.$stats['review']);

        return self::SUCCESS;
    }
}
