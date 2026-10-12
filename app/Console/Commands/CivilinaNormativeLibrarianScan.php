<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeLibrarianScanner;
use Illuminate\Console\Command;

class CivilinaNormativeLibrarianScan extends Command
{
    protected $signature = 'civilina:normative-librarian:scan
        {--path= : Dossier racine des normes}
        {--limit=100 : Nombre maximal de documents}
        {--all : Scanner tous les documents}
        {--force : Refaire les fichiers inchangés}';

    protected $description = 'Scanne, lit et classe la bibliothèque normative locale.';

    public function handle(NormativeLibrarianScanner $scanner): int
    {
        $path = $this->option('path') ?: null;
        $limit = $this->option('all') ? null : max(1,(int)$this->option('limit'));

        $stats = $scanner->scan($path,$limit,(bool)$this->option('force'));

        $this->info('Civilina Normative Librarian - scan terminé.');
        foreach ($stats as $key=>$value) $this->line(str_pad($key,16).' : '.$value);

        return self::SUCCESS;
    }
}
