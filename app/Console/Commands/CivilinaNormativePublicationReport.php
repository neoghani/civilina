<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeAtomicPublicationGateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CivilinaNormativePublicationReport extends Command
{
    protected $signature = 'civilina:normative-registry:report {--run= : ID du run, sinon dernier run}';
    protected $description = 'Produit un rapport JSON du Gate et du registre normatif.';

    public function handle(NormativeAtomicPublicationGateService $gate): int
    {
        $runQuery = DB::table('civilina_normative_publication_runs')->orderByDesc('id');
        if ($this->option('run') !== null) {
            $runQuery->where('id', (int) $this->option('run'));
        }

        $run = $runQuery->first();
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'gate' => $gate->diagnostics(),
            'registry' => [
                'active' => DB::table('civilina_normative_registry_requirements')->where('publication_state', 'ACTIVE')->count(),
                'revoked' => DB::table('civilina_normative_registry_requirements')->where('publication_state', 'REVOKED')->count(),
                'revisions' => DB::table('civilina_normative_registry_revisions')->count(),
                'events' => DB::table('civilina_normative_publication_events')->count(),
            ],
            'run' => $run,
        ];

        $dir = storage_path('app/private/engineering/reports/normative-publication');
        File::ensureDirectoryExists($dir);
        $path = $dir.DIRECTORY_SEPARATOR.'status_'.now()->format('Ymd_His').'.json';
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info($path);

        return self::SUCCESS;
    }
}
