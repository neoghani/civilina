<?php

namespace App\Services\Engineering\NormativeLibrarian;

use App\Models\NormativeLibrarianDocument;
use Illuminate\Support\Facades\File;

class NormativeOcrQueueExporter
{
    /**
     * Exporte une file OCR sans modifier les PDF.
     *
     * @return array{path:string,count:int}
     */
    public function export(?string $destination = null): array
    {
        $root = rtrim(
            $destination ?: 'D:\\Civilina_Normes_Classees\\_CIVILINA_OCR',
            '\\/'
        );

        File::ensureDirectoryExists($root);

        $path = $root.DIRECTORY_SEPARATOR
            .'ocr_queue_'.now()->format('Ymd_His').'.csv';

        $handle = fopen($path,'wb');

        if (! $handle) {
            throw new \RuntimeException('Impossible de créer la file OCR : '.$path);
        }

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'id','reference','filename','source_collection',
            'source_path','relative_path','sha256'
        ], ';');

        $count = 0;

        NormativeLibrarianDocument::query()
            ->where('extraction_status','needs_ocr')
            ->where('document_kind','!=','rejected')
            ->orderBy('id')
            ->get()
            ->each(function ($document) use ($handle,&$count) {
                fputcsv($handle, [
                    $document->id,
                    $document->reference_code,
                    $document->filename,
                    $document->source_collection,
                    $document->source_path,
                    $document->relative_path,
                    $document->sha256,
                ], ';');

                $count++;
            });

        fclose($handle);

        return ['path'=>$path,'count'=>$count];
    }
}
