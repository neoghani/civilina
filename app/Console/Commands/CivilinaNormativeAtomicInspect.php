<?php
namespace App\Console\Commands;
use Illuminate\Console\Command; use Illuminate\Support\Facades\DB;
class CivilinaNormativeAtomicInspect extends Command {
 protected $signature='civilina:normative-atomic:inspect {reference?} {--limit=20}'; protected $description='Inspecte le staging atomique';
 public function handle(): int {$q=DB::table('civilina_normative_atomic_statements')->orderBy('reference_code')->orderBy('section_code')->orderBy('sequence'); if($r=trim((string)$this->argument('reference')))$q->where('reference_code','like','%'.$r.'%'); foreach($q->limit(max(1,(int)$this->option('limit')))->get() as $x){$this->newLine();$this->line('#'.$x->id.' | '.$x->reference_code.' | '.$x->edition_key.' | §'.$x->section_code.' | '.$x->statement_type);$this->line($x->statement_text);} return self::SUCCESS;}
}
