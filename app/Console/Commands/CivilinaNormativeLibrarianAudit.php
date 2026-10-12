<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeClassificationAuditor;
use Illuminate\Console\Command;

class CivilinaNormativeLibrarianAudit extends Command
{
    protected $signature = 'civilina:normative-librarian:audit
        {--limit=500 : Nombre maximal de documents}
        {--status=AUTO_CLASSIFIED : Statut à auditer}
        {--dry-run : Auditer sans rétrograder les classifications risquées}';

    protected $description = 'Audit de précision des classifications normatives automatiques.';

    public function handle(NormativeClassificationAuditor $auditor): int
    {
        $stats = $auditor->audit(
            max(1, (int) $this->option('limit')),
            (string) $this->option('status'),
            ! (bool) $this->option('dry-run')
        );

        $this->info('Audit de classification terminé.');

        foreach ($stats as $key=>$value) {
            $this->line(str_pad($key, 26).' : '.$value);
        }

        return self::SUCCESS;
    }
}
