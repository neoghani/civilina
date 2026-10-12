<?php
namespace App\Services\Engineering\NormativeLibrarian;

class NormativeCandidateQualityService
{
    public function classify(string $text): array
    {
        $text = trim(preg_replace('/\s+/u',' ',$text));
        $lower = mb_strtolower($text);

        $markers = ['ne doit pas','ne doivent pas','doit','doivent','devra','devront','est requis','sont requis','shall not','must not','shall','must','should'];
        $marker = null;
        foreach ($markers as $m) {
            if (str_contains($lower, mb_strtolower($m))) { $marker=$m; break; }
        }

        $limit = preg_match('/(?:±|≤|≥|<|>|\bminimum\b|\bmaximum\b|\bmin\.?\b|\bmax\.?\b|\bau moins\b|\bau plus\b|ne doit pas dépasser|ne doivent pas dépasser|inférieur(?:e)?|supérieur(?:e)?|tolérance|écart admissible|not exceed|at least|at most)/ui',$text) === 1;

        $numeric = preg_match('/\b\d+(?:[.,]\d+)?\s*(?:mm|cm|m|m2|m²|m3|m³|mpa|n\/mm2|n\/mm²|kn|%|°c|kg|kg\/m3|kg\/m³|h|jour|jours)\b/ui',$text) === 1;

        if ($numeric && $limit) $type='TOLERANCE';
        elseif ($marker) $type='REQUIREMENT';
        elseif ($numeric) $type='REFERENCE_VALUE';
        else $type='OTHER';

        return [
            'type'=>$type,
            'marker'=>$marker,
            'publishable'=>in_array($type,['REQUIREMENT','TOLERANCE'],true),
        ];
    }

    public function editionIsKnown(?string $edition): bool
    {
        $v=mb_strtoupper(trim((string)$edition));
        if ($v==='') return false;
        foreach (['ANNEE_INCONNUE','ANNÉE_INCONNUE','INCONNUE','UNKNOWN','UNDETERMINED'] as $bad) {
            if (str_contains($v,$bad)) return false;
        }
        return true;
    }
}
