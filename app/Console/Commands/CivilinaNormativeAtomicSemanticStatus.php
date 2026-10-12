<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CivilinaNormativeAtomicSemanticStatus extends Command
{
    protected $signature = 'civilina:normative-atomic:semantic-status';
    protected $description = 'Bilan Semantic V2 + Source Authority.';

    public function handle(): int
    {
        $this->line('statements : '.DB::table('civilina_normative_atomic_statements')->count());

        foreach ([
            'REQUIREMENT','TOLERANCE','RECOMMENDATION','PERMISSION',
            'PROCEDURE_CANDIDATE','LIMIT_CANDIDATE','REFERENCE_VALUE','FORMULA_TABLE','OTHER'
        ] as $type) {
            $this->line(
                str_pad(strtolower($type), 22).' : '.
                DB::table('civilina_normative_atomic_statements')
                    ->where('statement_type',$type)->count()
            );
        }

        $this->newLine();

        foreach ([
            'BLOCKED_EDITION','SECONDARY_DETECTED','PRIMARY_CANDIDATE','UNVERIFIED'
        ] as $authority) {
            $this->line(
                str_pad(strtolower($authority), 22).' : '.
                DB::table('civilina_normative_atomic_source_reviews')
                    ->where('detected_authority',$authority)->count()
            );
        }

        return self::SUCCESS;
    }
}
