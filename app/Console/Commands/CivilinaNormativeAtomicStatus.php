<?php
namespace App\Console\Commands;
use Illuminate\Console\Command; use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Schema;
class CivilinaNormativeAtomicStatus extends Command {
 protected $signature='civilina:normative-atomic:status'; protected $description='Status segmentation atomique';
 public function handle(): int {if(!Schema::hasTable('civilina_normative_atomic_statements'))return self::FAILURE; $this->line('statements : '.DB::table('civilina_normative_atomic_statements')->count()); foreach(['REQUIREMENT','TOLERANCE','REFERENCE_VALUE','FORMULA_TABLE','OTHER'] as $t)$this->line(str_pad(strtolower($t),17).' : '.DB::table('civilina_normative_atomic_statements')->where('statement_type',$t)->count()); return self::SUCCESS;}
}
