<?php

namespace App\Services\Engineering\Normative;

use Illuminate\Support\Str;

class NormativeTaxonomyService
{
    public function classify(string $relativePath, ?string $title = null, ?string $existingDomain = null): array
    {
        $clean = str_replace('\\', '/', trim($relativePath, '/'));
        $segments = array_values(array_filter(explode('/', $clean), fn ($v) => trim($v) !== ''));

        $category = $segments[0] ?? 'Divers';
        $subcategory = count($segments) > 2 ? $segments[1] : null;

        // Les anciennes archives "yacine" contiennent plusieurs sous-bibliothèques.
        if (Str::lower(Str::ascii($category)) === 'yacine') {
            foreach (array_slice($segments, 1, -1) as $segment) {
                $ascii = Str::lower(Str::ascii($segment));
                if (! in_array($ascii, ['norme', 'les normes', 'norme et essai', 'yacine 22'], true)) {
                    $category = $segment;
                    break;
                }
            }
        }

        $haystack = Str::lower(Str::ascii(implode(' ', [$clean, (string) $title, (string) $existingDomain])));
        $domain = $this->detectDomain($haystack) ?: $existingDomain;

        return [
            'category' => mb_substr($category ?: 'Divers', 0, 190),
            'subcategory' => $subcategory ? mb_substr($subcategory, 0, 190) : null,
            'domain' => $domain,
        ];
    }

    public function detectDomain(string $text): ?string
    {
        $text = Str::lower(Str::ascii($text));

        $rules = [
            'concrete' => ['beton','ciment','coffrage','etaiement','armature','ferraillage','granulat','adjuvant','mortier','12350','12390','206-1','206 1'],
            'steel' => ['charpente','acier','assemblage','boulon','serrage','poutre','poteau metall','solive','lierne','contrevent'],
            'welding' => ['soudure','soudage','welding','iso soudure'],
            'earthworks_road' => ['chaussee','route','terrassement','remblai','cdf','compactage','troxler','deflexion','portance','corps de chaussee','cetu','cftr','gtr'],
            'metrology' => ['metrologie','etalonnage','thermometre','x07-'],
            'quality_management' => ['iso 9001','iso_9001','iso9001','iso 10005','iso_10005'],
            'facade' => ['facade','bardage','enveloppe'],
            'sampling_laboratory' => ['echantillonnage','prelevement','laboratoire','labo '],
            'general_referential' => ['fascicule','cctg','cctp','dtu '],
        ];

        foreach ($rules as $domain => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $domain;
                }
            }
        }

        return null;
    }

    public function label(?string $domain): string
    {
        return match ($domain) {
            'concrete' => 'Béton / BA',
            'steel' => 'Charpente métallique',
            'welding' => 'Soudage',
            'earthworks_road' => 'Terrassement / Route',
            'metrology' => 'Métrologie',
            'quality_management' => 'Management qualité',
            'facade' => 'Façade / Enveloppe',
            'sampling_laboratory' => 'Échantillonnage / Laboratoire',
            'general_referential' => 'Référentiel général',
            default => $domain ?: 'Non classé',
        };
    }
}
