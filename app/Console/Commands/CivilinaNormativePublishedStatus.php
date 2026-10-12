<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CivilinaNormativePublishedStatus extends Command
{
    protected $signature = 'civilina:normative-registry:status';
    protected $description = 'État du registre normatif publié Civilina.';

    public function handle(): int
    {
        if (! Schema::hasTable('civilina_normative_registry_requirements')) {
            $this->error('Registre publié absent.');
            return self::FAILURE;
        }

        $this->line('active_requirements  : '.DB::table('civilina_normative_registry_requirements')->where('publication_state','ACTIVE')->count());
        $this->line('revoked_requirements : '.DB::table('civilina_normative_registry_requirements')->where('publication_state','REVOKED')->count());
        $this->line('requirements         : '.DB::table('civilina_normative_registry_requirements')->where('publication_state','ACTIVE')->where('requirement_type','REQUIREMENT')->count());
        $this->line('tolerances           : '.DB::table('civilina_normative_registry_requirements')->where('publication_state','ACTIVE')->where('requirement_type','TOLERANCE')->count());
        $this->line('references           : '.DB::table('civilina_normative_registry_requirements')->where('publication_state','ACTIVE')->distinct('reference_code')->count('reference_code'));
        $this->line('publication_events   : '.DB::table('civilina_normative_publication_events')->count());
        $this->line('atomic_revisions     : '.DB::table('civilina_normative_registry_revisions')->count());

        return self::SUCCESS;
    }
}
