<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeAtomicPublicationGateService;
use Illuminate\Console\Command;

class CivilinaNormativeAtomicPublishDiagnostic extends Command
{
    protected $signature = 'civilina:normative-atomic:publish-diagnostic';
    protected $description = 'Affiche les blocages du Gate de publication atomique V1.';

    public function handle(NormativeAtomicPublicationGateService $gate): int
    {
        foreach ($gate->diagnostics() as $key => $value) {
            $this->line(str_pad($key, 24).' : '.$value);
        }

        return self::SUCCESS;
    }
}
