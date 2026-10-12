<?php
namespace App\Console\Commands;
use App\Services\Engineering\NormativeLibrarian\NormativeAtomicSegmentationService;
use Illuminate\Console\Command;
class CivilinaNormativeAtomicRun extends Command {
 protected $signature='civilina:normative-atomic:run {--limit=10} {--all}'; protected $description='Segmentation atomique normative';
 public function handle(NormativeAtomicSegmentationService $s): int {$r=$s->run($this->option('all')?null:max(1,(int)$this->option('limit'))); foreach($r as $k=>$v)$this->line(str_pad($k,18).' : '.$v); return $r['errors']?self::FAILURE:self::SUCCESS;}
}
