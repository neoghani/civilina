<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CivilinaNormativeDeepStatus extends Command
{
    protected $signature = 'civilina:normative-deep:status';
    protected $description = 'État de l’extraction profonde normative.';

    public function handle(): int
    {
        if (! Schema::hasTable('civilina_normative_deep_documents')) {
            $this->error('Tables deep extraction absentes.');
            return self::FAILURE;
        }

        $this->line('documents           : '.DB::table('civilina_normative_deep_documents')->count());
        $this->line('success             : '.DB::table('civilina_normative_deep_documents')->where('status','SUCCESS')->count());
        $this->line('partial             : '.DB::table('civilina_normative_deep_documents')->where('status','PARTIAL')->count());
        $this->line('failed              : '.DB::table('civilina_normative_deep_documents')->where('status','FAILED')->count());
        $this->line('sections            : '.DB::table('civilina_normative_deep_sections')->count());
        $this->line('requirement_candidates : '.DB::table('civilina_normative_deep_requirement_candidates')->where('candidate_type','REQUIREMENT')->count());
        $this->line('tolerance_candidates   : '.DB::table('civilina_normative_deep_requirement_candidates')->where('candidate_type','TOLERANCE')->count());
        $this->line('validated           : '.DB::table('civilina_normative_deep_requirement_candidates')->where('status','VALIDATED')->count());

        return self::SUCCESS;
    }
}
