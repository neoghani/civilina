<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeRegistryPublisher;
use Illuminate\Console\Command;

class CivilinaNormativeLibrarianPublish extends Command
{
    protected $signature = 'civilina:normative-librarian:publish';
    protected $description = 'Publie les classifications validées dans le registre normatif Civilina existant.';

    public function handle(NormativeRegistryPublisher $publisher): int
    {
        $stats = $publisher->publishAll();
        $this->info('Publication vers le registre Civilina terminée.');
        foreach ($stats as $key=>$value) $this->line(str_pad($key,16).' : '.$value);
        return self::SUCCESS;
    }
}
