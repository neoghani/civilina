<?php

namespace App\Console\Commands;

use App\Models\NormativeLibrarianDocument;
use Illuminate\Console\Command;

class CivilinaNormativeOcrResetLanguage extends Command
{
    protected $signature = 'civilina:normative-ocr:reset-language
        {language=eng : Langue OCR à remettre dans la file}
        {--all : Inclure aussi les documents ayant déjà une référence}';

    protected $description = 'Remet dans NEEDS_OCR les OCR réalisés avec une langue à remplacer.';

    public function handle(): int
    {
        $language = trim((string) $this->argument('language'));

        $query = NormativeLibrarianDocument::query()
            ->where('ocr_status', 'SUCCESS')
            ->where('ocr_language', $language);

        if (! $this->option('all')) {
            $query->where(function ($q) {
                $q->whereNull('reference_code')
                    ->orWhere('reference_code', '');
            });
        }

        $count = $query->count();

        $query->update([
            'extraction_status'=>'needs_ocr',
            'ocr_status'=>'REPROCESS',
            'ocr_last_error'=>'Re-OCR demandé après installation d’une langue plus appropriée.',
        ]);

        $this->info('OCR remis en file.');
        $this->line('language              : '.$language);
        $this->line('documents_requeued    : '.$count);

        return self::SUCCESS;
    }
}
