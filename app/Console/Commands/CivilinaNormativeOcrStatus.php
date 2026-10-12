<?php

namespace App\Console\Commands;

use App\Models\NormativeLibrarianDocument;
use App\Services\Engineering\NormativeLibrarian\NormativeOcrToolchain;
use Illuminate\Console\Command;

class CivilinaNormativeOcrStatus extends Command
{
    protected $signature = 'civilina:normative-ocr:status';

    protected $description = 'Vérifie les outils et l’état de la file OCR normative.';

    public function handle(NormativeOcrToolchain $toolchain): int
    {
        $tools = $toolchain->inspect();

        $this->line('ready             : '.($tools['ready'] ? 'YES' : 'NO'));
        $this->line('tesseract         : '.$tools['tesseract']);
        $this->line('pdftoppm          : '.$tools['pdftoppm']);
        $this->line('language          : '.($tools['selected_language'] ?: 'NONE'));
        $this->line('languages         : '.implode(', ', $tools['languages']));
        $this->line('needs_ocr         : '.NormativeLibrarianDocument::query()
            ->where('extraction_status','needs_ocr')->count());
        $this->line('ocr_success       : '.NormativeLibrarianDocument::query()
            ->where('ocr_status','SUCCESS')->count());
        $this->line('ocr_low_text      : '.NormativeLibrarianDocument::query()
            ->where('ocr_status','LOW_TEXT')->count());
        $this->line('ocr_failed        : '.NormativeLibrarianDocument::query()
            ->where('ocr_status','FAILED')->count());

        return $tools['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
