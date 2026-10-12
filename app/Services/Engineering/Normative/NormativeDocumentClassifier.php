<?php

namespace App\Services\Engineering\Normative;

use Illuminate\Support\Str;

class NormativeDocumentClassifier
{
    /**
     * @return array{type:string, rejected:bool, reason:?string}
     */
    public function classify(string $filename, ?string $detectedCode, ?string $sampleText = null): array
    {
        $name = Str::lower(Str::ascii($filename));
        $sample = Str::lower(Str::ascii((string) $sampleText));

        $rejectTerms = [
            'cv ', 'curriculum', 'candidat', 'lettre de motivation',
            'resume ', 'yassine.doc', 'yacine.doc',
        ];

        foreach ($rejectTerms as $term) {
            if (str_contains(' '.$name, $term) || str_contains(' '.$sample, $term)) {
                return ['type' => 'rejected', 'rejected' => true, 'reason' => 'Document non normatif détecté (CV/lettre/candidature).'];
            }
        }

        if (preg_match('/(^|[\/\\\\])ft[_\-\s]/i', $filename) || str_contains($name, 'fiche technique')) {
            return ['type' => 'technical_sheet', 'rejected' => false, 'reason' => null];
        }

        if (str_contains($name, 'cours') || str_contains($name, 'mecanique sols') || str_contains($name, 'mecanique-sols')) {
            return ['type' => 'course', 'rejected' => false, 'reason' => null];
        }

        if (preg_match('/\b(gtr|cftr|cctp|dtr|guide)\b/i', $filename)) {
            return ['type' => 'referential', 'rejected' => false, 'reason' => null];
        }

        if ($detectedCode) {
            return ['type' => 'standard', 'rejected' => false, 'reason' => null];
        }

        return ['type' => 'unclassified', 'rejected' => false, 'reason' => null];
    }
}
