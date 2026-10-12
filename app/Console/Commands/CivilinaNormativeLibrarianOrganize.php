<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativePhysicalLibraryOrganizer;
use Illuminate\Console\Command;

class CivilinaNormativeLibrarianOrganize extends Command
{
    protected $signature = 'civilina:normative-librarian:organize
        {--destination= : Dossier physique classé}
        {--mode=hardlink : hardlink ou copy}
        {--clean : Reconstruire le dossier Civilina existant}
        {--limit= : Limite facultative de documents}';

    protected $description = 'Construit un dossier physique de normes classées sans déplacer les originaux.';

    public function handle(NormativePhysicalLibraryOrganizer $organizer): int
    {
        $limit = $this->option('limit');

        $stats = $organizer->build(
            $this->option('destination') ?: null,
            $this->option('mode') ?: null,
            (bool)$this->option('clean'),
            $limit !== null ? max(1, (int)$limit) : null
        );

        $this->info('Bibliothèque normative physique construite.');

        foreach ($stats as $key=>$value) {
            $this->line(str_pad($key, 28).' : '.$value);
        }

        return self::SUCCESS;
    }
}
