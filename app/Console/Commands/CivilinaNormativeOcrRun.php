<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeOcrService;
use Illuminate\Console\Command;

class CivilinaNormativeOcrRun extends Command
{
    protected $signature = 'civilina:normative-ocr:run
        {--limit=20 : Nombre de PDF à traiter}
        {--all : Traiter toute la file éligible}
        {--max-pages= : Nombre de premières pages à OCR}
        {--force : Retenter FAILED / LOW_TEXT}';

    protected $description = 'OCR local des PDF normatifs sans couche texte.';

    public function handle(NormativeOcrService $service): int
    {
        $limit = (bool) $this->option('all')
            ? null
            : max(1, (int) $this->option('limit'));

        $maxPages = $this->option('max-pages');

        $stats = $service->run(
            $limit,
            $maxPages !== null ? max(1, (int) $maxPages) : null,
            (bool) $this->option('force')
        );

        $this->info('OCR normatif terminé.');

        foreach ($stats as $key=>$value) {
            $this->line(str_pad($key, 20).' : '.(is_scalar($value) ? $value : json_encode($value)));
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
