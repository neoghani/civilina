<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeClassificationRefiner;
use Illuminate\Console\Command;

class CivilinaNormativeLibrarianRefine extends Command
{
    protected $signature = 'civilina:normative-librarian:refine
        {--limit=500 : Nombre maximal de documents}
        {--status=UNCLASSIFIED : Statut source à retraiter}';

    protected $description = 'Deuxième passe de classement des normes non classées ou à revoir.';

    public function handle(NormativeClassificationRefiner $refiner): int
    {
        $stats = $refiner->run(
            max(1, (int) $this->option('limit')),
            (string) $this->option('status')
        );

        $this->info('Deuxième passe Normative Librarian terminée.');

        foreach ($stats as $key => $value) {
            $this->line(str_pad($key, 20).' : '.$value);
        }

        return self::SUCCESS;
    }
}
