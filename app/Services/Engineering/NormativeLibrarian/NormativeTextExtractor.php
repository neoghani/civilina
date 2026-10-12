<?php

namespace App\Services\Engineering\NormativeLibrarian;

use ZipArchive;

class NormativeTextExtractor
{
    public function extract(string $path, string $extension, int $coverPages = 4): array
    {
        $extension = strtolower($extension);

        try {
            return match ($extension) {
                'pdf' => $this->pdf($path, $coverPages),
                'docx' => $this->docx($path),
                'txt', 'md' => $this->plain($path),
                default => ['status'=>'unsupported','text'=>'','pages'=>0,'message'=>'Format non pris en charge.'],
            };
        } catch (\Throwable $e) {
            report($e);
            return ['status'=>'error','text'=>'','pages'=>0,'message'=>$e->getMessage()];
        }
    }

    private function pdf(string $path, int $coverPages): array
    {
        if (! class_exists(\Smalot\PdfParser\Parser::class)) {
            return ['status'=>'needs_parser','text'=>'','pages'=>0,'message'=>'smalot/pdfparser absent.'];
        }

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($path);
        $pages = $pdf->getPages();
        $texts = [];
        $count = min(max(1, $coverPages), count($pages));

        for ($i = 0; $i < $count; $i++) {
            $text = $this->clean($pages[$i]->getText());
            if ($text !== '') $texts[] = $text;
        }

        $joined = trim(implode("\n\n--- PAGE ---\n\n", $texts));

        if ($joined === '') {
            return [
                'status'=>'needs_ocr','text'=>'','pages'=>$count,
                'message'=>'Aucun texte exploitable sur les premières pages. OCR requis.'
            ];
        }

        return [
            'status'=>'extracted',
            'text'=>mb_substr($joined, 0, 50000),
            'pages'=>$count,
            'message'=>null
        ];
    }

    private function docx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            return ['status'=>'needs_parser','text'=>'','pages'=>0,'message'=>'ZipArchive absent.'];
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return ['status'=>'error','text'=>'','pages'=>0,'message'=>'Impossible d’ouvrir le DOCX.'];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml)) {
            return ['status'=>'error','text'=>'','pages'=>0,'message'=>'word/document.xml absent.'];
        }

        $xml = str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], $xml);
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = $this->clean($text);

        return [
            'status'=>$text === '' ? 'needs_ocr' : 'extracted',
            'text'=>mb_substr($text, 0, 50000),
            'pages'=>1,
            'message'=>$text === '' ? 'Aucun texte exploitable.' : null
        ];
    }

    private function plain(string $path): array
    {
        $text = $this->clean((string) @file_get_contents($path));
        return [
            'status'=>$text === '' ? 'error' : 'extracted',
            'text'=>mb_substr($text, 0, 50000),
            'pages'=>1,
            'message'=>$text === '' ? 'Fichier vide ou illisible.' : null
        ];
    }

    private function clean(string $value): string
    {
        $value = str_replace("\0", '', $value);
        $value = preg_replace('/[ \t]+\R/u', "\n", $value);
        $value = preg_replace('/\R{3,}/u', "\n\n", $value);
        $value = preg_replace('/[ \t]{2,}/u', ' ', $value);
        return trim((string) $value);
    }
}
