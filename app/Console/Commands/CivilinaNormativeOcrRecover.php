<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class CivilinaNormativeOcrRecover extends Command
{
    protected $signature = 'civilina:normative-ocr:recover';

    protected $description = 'Relance la récupération des références puis canonicalisation et consensus après OCR.';

    public function handle(): int
    {
        $commands = [
            ['civilina:normative-librarian:recover-references', []],
            ['civilina:normative-librarian:canonicalize', []],
            ['civilina:normative-librarian:consensus', []],
        ];

        foreach ($commands as [$command,$arguments]) {
            $this->newLine();
            $this->info('> php artisan '.$command);

            $exit = Artisan::call($command, $arguments);
            $this->output->write(Artisan::output());

            if ($exit !== 0) {
                $this->error('Échec : '.$command);
                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('Références / canonicalisation / consensus mis à jour.');

        return self::SUCCESS;
    }
}
