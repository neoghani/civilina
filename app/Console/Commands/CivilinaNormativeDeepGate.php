<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CivilinaNormativeDeepGate extends Command
{
    protected $signature = 'civilina:normative-deep:gate';
    protected $description = 'Gate humain avant publication normative';

    public function handle(): int
    {
        if (!Schema::hasTable('civilina_normative_deep_requirement_candidates')) {
            $this->error('Tables Deep Extraction absentes.');
            return self::FAILURE;
        }

        foreach (['CANDIDATE','VALIDATED','REJECTED'] as $s) {
            $this->line(strtolower($s).' : '.DB::table('civilina_normative_deep_requirement_candidates')->where('status',$s)->count());
        }

        $validated = DB::table('civilina_normative_deep_requirement_candidates')->where('status','VALIDATED')->count();

        if ($validated === 0) {
            $this->warn('GATE : EN ATTENTE — aucune validation humaine.');
            return self::FAILURE;
        }

        $this->info('GATE : VERT — publication contrôlée possible au Block 2K.5.2.');
        return self::SUCCESS;
    }
}
