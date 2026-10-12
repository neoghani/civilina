<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeCanonicalizer;
use Illuminate\Console\Command;

class CivilinaNormativeLibrarianCanonicalize extends Command
{
    protected $signature = 'civilina:normative-librarian:canonicalize
        {--limit= : Limite facultative}';

    protected $description = 'Regroupe les normes par référence canonique et édition.';

    public function handle(NormativeCanonicalizer $canonicalizer): int
    {
        $limit = $this->option('limit');

        $stats = $canonicalizer->run(
            $limit !== null ? max(1, (int)$limit) : null
        );

        $this->info('Canonicalisation normative terminée.');

        foreach ($stats as $key=>$value) {
            $this->line(str_pad($key, 24).' : '.$value);
        }

        return self::SUCCESS;
    }
}
