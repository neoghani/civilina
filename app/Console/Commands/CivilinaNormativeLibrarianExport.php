<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeManifestExporter;
use Illuminate\Console\Command;

class CivilinaNormativeLibrarianExport extends Command
{
    protected $signature = 'civilina:normative-librarian:export {--output= : Chemin du manifeste JSON}';
    protected $description = 'Exporte le catalogue normatif Civilina en JSON.';

    public function handle(NormativeManifestExporter $exporter): int
    {
        $path = $exporter->export($this->option('output') ?: null);
        $this->info('Manifeste créé : '.$path);
        return self::SUCCESS;
    }
}
