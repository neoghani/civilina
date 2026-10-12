<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeOcrQueueExporter;
use Illuminate\Console\Command;

class CivilinaNormativeLibrarianOcrQueue extends Command
{
    protected $signature = 'civilina:normative-librarian:ocr-queue
        {--destination= : Dossier CSV OCR}';

    protected $description = 'Exporte la liste des PDF nécessitant OCR.';

    public function handle(NormativeOcrQueueExporter $exporter): int
    {
        $result = $exporter->export(
            $this->option('destination') ?: null
        );

        $this->info('File OCR exportée.');
        $this->line('count : '.$result['count']);
        $this->line('path  : '.$result['path']);

        return self::SUCCESS;
    }
}
