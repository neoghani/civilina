<?php

namespace App\Services\Engineering\Normative;

use Illuminate\Support\Str;

class NormativeDomainClassifier
{
    public function detect(string $text): ?string
    {
        $text = Str::lower(Str::ascii($text));
        $scores = [];

        foreach ((array) config('civilina_normative.domain_keywords', []) as $domain => $keywords) {
            $scores[$domain] = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($text, Str::lower(Str::ascii($keyword)))) {
                    $scores[$domain]++;
                }
            }
        }

        arsort($scores);
        $domain = array_key_first($scores);

        return $domain && ($scores[$domain] ?? 0) > 0 ? $domain : null;
    }
}
