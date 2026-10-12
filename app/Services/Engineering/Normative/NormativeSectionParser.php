<?php

namespace App\Services\Engineering\Normative;

class NormativeSectionParser
{
    /**
     * @param array<int, array{page:int,text:string}> $pages
     * @return array<int, array{section_code:?string, heading:?string, body:string, page_number:?int, sequence:int}>
     */
    public function parse(array $pages): array
    {
        $sections = [];
        $sequence = 0;

        foreach ($pages as $page) {
            $pageNo = (int) ($page['page'] ?? 0);
            $text = trim((string) ($page['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $lines = preg_split('/\R/u', $text) ?: [];
            $current = [
                'section_code' => null,
                'heading' => null,
                'body' => '',
                'page_number' => $pageNo ?: null,
                'sequence' => $sequence++,
            ];

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    $current['body'] .= "\n";
                    continue;
                }

                $heading = $this->detectHeading($line);
                if ($heading) {
                    if (trim($current['body']) !== '') {
                        $current['body'] = trim($current['body']);
                        $sections[] = $current;
                    }

                    $current = [
                        'section_code' => $heading['code'],
                        'heading' => $heading['heading'],
                        'body' => '',
                        'page_number' => $pageNo ?: null,
                        'sequence' => $sequence++,
                    ];
                    continue;
                }

                $current['body'] .= ($current['body'] === '' ? '' : "\n").$line;
            }

            if (trim($current['body']) !== '') {
                $current['body'] = trim($current['body']);
                $sections[] = $current;
            }
        }

        return $sections;
    }

    /**
     * @return array{code:?string,heading:string}|null
     */
    private function detectHeading(string $line): ?array
    {
        $patterns = [
            '/^\s*(\d+(?:\.\d+){0,4})\s+(.{3,160})$/u',
            '/^\s*(Article\s+\d+(?:\.\d+)*)\s*[:\-–—]?\s*(.{0,160})$/ui',
            '/^\s*(Annexe\s+[A-Z0-9]+)\s*[:\-–—]?\s*(.{0,160})$/ui',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line, $m)) {
                return [
                    'code' => trim($m[1]),
                    'heading' => trim($m[2] ?? $line),
                ];
            }
        }

        return null;
    }
}
