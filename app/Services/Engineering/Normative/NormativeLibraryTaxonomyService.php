<?php

namespace App\Services\Engineering\Normative;

use App\Models\EngineeringNormativeStandard;
use Illuminate\Support\Str;

class NormativeLibraryTaxonomyService
{
    public function disciplineLabel(?string $value): string
    {
        return match ($value) {
            'structures' => 'Structures',
            'infrastructure' => 'Infrastructures',
            'architecture_envelope' => 'Architecture & enveloppe',
            'quality_management' => 'Qualité & management',
            'testing_metrology' => 'Essais & métrologie',
            'general_referentials' => 'Référentiels généraux',
            default => 'À classer',
        };
    }

    public function domainLabel(?string $value): string
    {
        return match ($value) {
            'concrete' => 'Béton / béton armé',
            'steel' => 'Charpente métallique',
            'welding' => 'Soudage',
            'earthworks_road' => 'Terrassements & routes',
            'metrology' => 'Métrologie',
            'quality_management' => 'Management qualité',
            'facade' => 'Façades & enveloppe',
            'sampling_laboratory' => 'Échantillonnage & laboratoire',
            'general_referential' => 'Référentiels généraux',
            default => 'À classer',
        };
    }

    public function reclassifyAll(): array
    {
        $stats = ['total'=>0, 'classified'=>0, 'review'=>0];

        EngineeringNormativeStandard::query()
            ->with('sources:id,standard_id,relative_path')
            ->orderBy('id')
            ->chunkById(200, function ($standards) use (&$stats) {
                foreach ($standards as $standard) {
                    $stats['total']++;
                    $data = $this->classify($standard);

                    $standard->forceFill([
                        'discipline' => $data['discipline'],
                        'domain' => $data['domain'],
                        'subdomain' => $data['subdomain'],
                        'family_name' => $data['family_name'],
                        'source_collection' => $data['source_collection'],
                        'classification_status' => $data['discipline'] === 'unclassified' ? 'review' : 'auto',
                    ])->save();

                    if ($data['discipline'] === 'unclassified') $stats['review']++;
                    else $stats['classified']++;
                }
            });

        return $stats;
    }

    public function classify(EngineeringNormativeStandard $standard): array
    {
        $standard->loadMissing('sources');

        $paths = $standard->sources->pluck('relative_path')->filter()->values();
        $collection = $this->collection($paths->all());

        $text = Str::lower(Str::ascii(implode(' ', array_filter([
            $standard->canonical_code,
            $standard->title,
            $standard->issuing_body,
            $standard->category,
            $standard->subcategory,
            $standard->domain,
            $paths->implode(' '),
        ]))));

        [$discipline, $domain] = $this->disciplineDomain($text, $standard->domain);
        $subdomain = $this->subdomain($text, $domain);
        $family = $this->family((string) $standard->canonical_code, $subdomain);

        return [
            'discipline' => $discipline,
            'domain' => $domain,
            'subdomain' => $subdomain,
            'family_name' => $family,
            'source_collection' => $collection,
        ];
    }

    private function collection(array $paths): string
    {
        $counts = [];

        foreach ($paths as $path) {
            $path = str_replace('\\', '/', trim((string) $path, '/'));
            $parts = array_values(array_filter(explode('/', $path)));
            $name = count($parts) > 1 ? trim($parts[0]) : 'Racine / non classé';
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        if (! $counts) return 'Racine / non classé';

        arsort($counts);
        return (string) array_key_first($counts);
    }

    private function disciplineDomain(string $text, ?string $existing): array
    {
        if (in_array($existing, ['concrete','steel','welding'], true)) return ['structures', $existing];
        if ($existing === 'earthworks_road') return ['infrastructure', $existing];
        if ($existing === 'facade') return ['architecture_envelope', $existing];
        if ($existing === 'quality_management') return ['quality_management', $existing];
        if (in_array($existing, ['metrology','sampling_laboratory'], true)) return ['testing_metrology', $existing];
        if ($existing === 'general_referential') return ['general_referentials', $existing];

        if (preg_match('/\b(beton|ciment|coffrage|armature|ferraillage|granulat|adjuvant|mortier|12350|12390|206)\b/u', $text)) return ['structures','concrete'];
        if (preg_match('/\b(charpente|acier|assemblage|boulon|serrage|poutre|solive|lierne|contreventement)\b/u', $text)) return ['structures','steel'];
        if (preg_match('/\b(soudure|soudage|welding|9606|15614|1763)\b/u', $text)) return ['structures','welding'];
        if (preg_match('/\b(chaussee|route|terrassement|remblai|compactage|troxler|deflexion|portance|gtr|cftr|cetu|bitume|enrobe)\b/u', $text)) return ['infrastructure','earthworks_road'];
        if (preg_match('/\b(facade|bardage|enveloppe|knauf|platre)\b/u', $text)) return ['architecture_envelope','facade'];
        if (preg_match('/\b(iso 9001|iso9001|iso 10005|management qualite)\b/u', $text)) return ['quality_management','quality_management'];
        if (preg_match('/\b(metrologie|etalonnage|echantillonnage|prelevement|laboratoire)\b/u', $text)) return ['testing_metrology','sampling_laboratory'];
        if (preg_match('/\b(fascicule|cctg|cctp|dtu|guide)\b/u', $text)) return ['general_referentials','general_referential'];

        return ['unclassified','unclassified'];
    }

    private function subdomain(string $text, string $domain): string
    {
        if ($domain === 'concrete') {
            if (preg_match('/\b(ciment|liant|chaux)\b/u', $text)) return 'Ciments & liants';
            if (preg_match('/\b(granulat|sable|gravillon)\b/u', $text)) return 'Granulats';
            if (preg_match('/\b(beton frais|affaissement|slump|consistance|12350)\b/u', $text)) return 'Béton frais';
            if (preg_match('/\b(compression|resistance|eprouvette|12390)\b/u', $text)) return 'Béton durci';
            if (preg_match('/\b(coffrage|etaiement)\b/u', $text)) return 'Coffrage & étaiement';
            if (preg_match('/\b(armature|ferraillage|enrobage)\b/u', $text)) return 'Armatures';
            if (preg_match('/\b(adjuvant)\b/u', $text)) return 'Adjuvants';
            return 'Béton - général';
        }

        if ($domain === 'steel') {
            if (preg_match('/\b(boulon|serrage|couple|precontrainte)\b/u', $text)) return 'Assemblages boulonnés';
            if (preg_match('/\b(montage|tolerance|aplomb|alignement)\b/u', $text)) return 'Montage & tolérances';
            return 'Produits & éléments acier';
        }

        if ($domain === 'welding') {
            if (preg_match('/\b(9606|qualification soudeur)\b/u', $text)) return 'Qualification des soudeurs';
            if (preg_match('/\b(15614|wps|qmosp|mode operatoire)\b/u', $text)) return 'Modes opératoires de soudage';
            if (preg_match('/\b(cnd|ndt|controle non destructif)\b/u', $text)) return 'Contrôles non destructifs';
            return 'Soudage - général';
        }

        if ($domain === 'earthworks_road') {
            if (preg_match('/\b(terrassement|remblai|compactage|troxler|proctor)\b/u', $text)) return 'Terrassements & compactage';
            if (preg_match('/\b(portance|plaque|ev1|ev2)\b/u', $text)) return 'Portance';
            if (preg_match('/\b(deflexion)\b/u', $text)) return 'Déflexion';
            if (preg_match('/\b(bitume|enrobe)\b/u', $text)) return 'Enrobés & bitumes';
            if (preg_match('/\b(chaussee|corps de chaussee)\b/u', $text)) return 'Corps de chaussée';
            return 'Routes & terrassements - général';
        }

        if ($domain === 'quality_management') return 'Systèmes de management';
        if ($domain === 'facade') return 'Façades & enveloppe';
        if ($domain === 'metrology') return 'Métrologie';
        if ($domain === 'sampling_laboratory') return 'Essais & laboratoire';
        if ($domain === 'general_referential') return 'CCTG / DTU / guides';

        return 'À classer';
    }

    private function family(string $code, string $fallback): string
    {
        if (preg_match('/^(NF\s+EN|NF\s+ISO|EN|ISO|NF|XP\s+P|P|DTU|GTR|CFTR)\s+([0-9]+)/i', trim($code), $m)) {
            return strtoupper(trim($m[1])).' '.$m[2];
        }
        return trim($code) ?: $fallback;
    }
}
