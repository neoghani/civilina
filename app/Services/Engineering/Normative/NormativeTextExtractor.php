<?php

namespace App\Services\Engineering\Normative;

use RuntimeException;
use ZipArchive;

class NormativeTextExtractor
{
    /**
     * @return array{status:string, pages:array<int, array{page:int,text:string}>, text:string, message:?string}
     */
    public function extract(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => $this->extractPdf($path),
            'docx' => $this->extractDocx($path),
            'txt', 'md' => $this->plainText($path),
            default => [
                'status' => 'unsupported',
                'pages' => [],
                'text' => '',
                'message' => 'Extension non supportée pour l’indexation de contenu.',
            ],
        };
    }

    private function extractPdf(string $path): array
    {
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($path);
                $pages = [];
                foreach ($pdf->getPages() as $index => $page) {
                    $pages[] = [
                        'page' => $index + 1,
                        'text' => $this->clean($page->getText()),
                    ];
                }
                $text = trim(implode("\n\n", array_column($pages, 'text')));

                return [
                    'status' => $text !== '' ? 'extracted' : 'needs_ocr',
                    'pages' => $pages,
                    'text' => $text,
                    'message' => $text !== '' ? null : 'PDF sans texte exploitable : OCR nécessaire.',
                ];
            } catch (\Throwable $e) {
                return [
                    'status' => 'extract_error',
                    'pages' => [],
                    'text' => '',
                    'message' => $e->getMessage(),
                ];
            }
        }

        $binary = $this->findPdftotext();
        if ($binary) {
            $tmp = tempnam(sys_get_temp_dir(), 'civilina_norm_');
            $cmd = sprintf(
                '"%s" -layout -enc UTF-8 "%s" "%s"',
                $binary,
                str_replace('"', '\"', $path),
                str_replace('"', '\"', $tmp)
            );
            @exec($cmd, $output, $exit);
            $text = $exit === 0 && is_file($tmp) ? $this->clean((string) file_get_contents($tmp)) : '';
            @unlink($tmp);

            return [
                'status' => $text !== '' ? 'extracted' : 'needs_pdf_parser',
                'pages' => $text !== '' ? [['page' => 1, 'text' => $text]] : [],
                'text' => $text,
                'message' => $text !== '' ? null : 'Installer smalot/pdfparser ou Poppler pdftotext.',
            ];
        }

        return [
            'status' => 'needs_pdf_parser',
            'pages' => [],
            'text' => '',
            'message' => 'Aucun extracteur PDF disponible.',
        ];
    }

    private function extractDocx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            return [
                'status' => 'extract_error',
                'pages' => [],
                'text' => '',
                'message' => 'Extension PHP ZipArchive indisponible.',
            ];
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [
                'status' => 'extract_error',
                'pages' => [],
                'text' => '',
                'message' => 'Impossible d’ouvrir le DOCX.',
            ];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml)) {
            return [
                'status' => 'extract_error',
                'pages' => [],
                'text' => '',
                'message' => 'word/document.xml absent.',
            ];
        }

        $xml = str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], $xml);
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = $this->clean($text);

        return [
            'status' => $text !== '' ? 'extracted' : 'empty',
            'pages' => $text !== '' ? [['page' => 1, 'text' => $text]] : [],
            'text' => $text,
            'message' => null,
        ];
    }

    private function plainText(string $path): array
    {
        $text = $this->clean((string) file_get_contents($path));

        return [
            'status' => $text !== '' ? 'extracted' : 'empty',
            'pages' => $text !== '' ? [['page' => 1, 'text' => $text]] : [],
            'text' => $text,
            'message' => null,
        ];
    }

    private function clean(string $text): string
    {
        $text = str_replace("\0", '', $text);
        $text = preg_replace("/[ \t]+\n/u", "\n", $text);
        $text = preg_replace("/\n{4,}/u", "\n\n", $text);
        return trim($text);
    }

    private function findPdftotext(): ?string
    {
        $candidates = ['pdftotext.exe', 'pdftotext'];
        foreach ($candidates as $candidate) {
            $result = [];
            @exec("where $candidate 2>NUL", $result, $exit);
            if ($exit === 0 && isset($result[0]) && is_file(trim($result[0]))) {
                return trim($result[0]);
            }
        }
        return null;
    }
}
