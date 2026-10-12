<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CivilinaNormativeAtomicInspectV2 extends Command
{
    protected $signature = 'civilina:normative-atomic:inspect-v2
        {reference? : Référence, ex. NF EN 206}
        {--limit=40}';

    protected $description = 'Inspecte les énoncés atomiques V2 avec autorité de source.';

    public function handle(): int
    {
        $q = DB::table('civilina_normative_atomic_statements')
            ->orderBy('reference_code')
            ->orderBy('section_code')
            ->orderBy('sequence');

        if ($ref = trim((string) $this->argument('reference'))) {
            $q->where('reference_code','like','%'.$ref.'%');
        }

        foreach ($q->limit(max(1,(int)$this->option('limit')))->get() as $r) {
            $this->newLine();
            $this->line(
                '#'.$r->id.' | '.$r->reference_code.' | '.$r->edition_key.
                ' | §'.$r->section_code.' | '.$r->statement_type.
                ' | SOURCE='.$r->source_authority
            );
            $this->line($r->statement_text);
        }

        return self::SUCCESS;
    }
}
