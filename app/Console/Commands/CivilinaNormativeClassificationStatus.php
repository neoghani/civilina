<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CivilinaNormativeClassificationStatus extends Command
{
    protected $signature = 'civilina:normative-classification:status';
    protected $description = 'Etat du modele de classification 2K.6 sans lancer de full run.';

    public function handle(): int
    {
        foreach (['CLASSIFIED', 'TO_REVIEW', 'UNCLASSIFIED'] as $status) {
            $this->line(str_pad(mb_strtolower($status), 16).' : '
                .DB::table('civilina_normative_classifications')
                    ->where('classification_status', $status)
                    ->count());
        }

        $this->line('gate             : HUMAN_REVIEW_REQUIRED');

        return self::SUCCESS;
    }
}
