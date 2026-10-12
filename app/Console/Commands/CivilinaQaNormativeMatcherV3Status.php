<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CivilinaQaNormativeMatcherV3Status extends Command
{
    protected $signature = 'civilina:qa-normative-v3:status';

    protected $description = 'Affiche le dernier état du Matcher QA/QC V3 sans écrire de données.';

    public function handle(): int
    {
        if (! Schema::hasTable('civilina_qa_normative_match_runs_v3')) {
            $this->error('Tables Matcher V3 absentes.');

            return self::FAILURE;
        }

        $run = DB::table('civilina_qa_normative_match_runs_v3')->orderByDesc('id')->first();
        if (! $run) {
            $this->line('run = NONE');

            return self::SUCCESS;
        }

        foreach ([
            'run_id' => $run->id,
            'status' => $run->status,
            'eligible_registry' => $run->eligible_registry_count,
            'MATCH' => $run->match_count,
            'PROPOSED' => $run->proposed_count,
            'NO_MATCH' => $run->no_match_count,
            'legacy_seen' => $run->legacy_seen,
            'legacy_preserved' => $run->legacy_preserved,
            'proposed_pending_review' => DB::table('civilina_qa_normative_matches_v3')
                ->where('result_status', 'PROPOSED')
                ->where('review_status', 'HUMAN_REVIEW_REQUIRED')
                ->count(),
        ] as $key => $value) {
            $this->line($key.' = '.$value);
        }

        return self::SUCCESS;
    }
}
