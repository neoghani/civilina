<?php

namespace App\Domain\CivilinaAI\Recognition;

use App\Domain\CivilinaAI\Registries\LexiconRegistry;

final readonly class ConceptRecognizer
{
    public function __construct(private LexiconRegistry $lexicon)
    {
    }

    public function normalize(string $text): string
    {
        $text = strtr(mb_strtolower($text), [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y',
            '’' => ' ', "'" => ' ', '×' => 'x',
        ]);
        $text = (string) preg_replace('/[^a-z0-9&]+/', ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    public function containsTerm(string $normalizedText, string $term): bool
    {
        $term = $this->normalize($term);

        return $term !== '' && str_contains(" {$normalizedText} ", " {$term} ");
    }

    public function recognize(string $text): array
    {
        $normalized = $this->normalize($text);
        $matches = [];
        foreach ($this->lexicon->entries() as $entry) {
            $matchedTerms = array_values(array_filter($entry['terms'], fn (string $term): bool => $this->containsTerm($normalized, $term)));
            if ($matchedTerms !== []) {
                $matches[] = [
                    'id' => $entry['id'],
                    'category' => $entry['category'],
                    'value' => $entry['value'],
                    'terms' => $matchedTerms,
                    'order' => $entry['order'],
                ];
            }
        }

        $byCategory = [];
        foreach ($matches as $match) {
            $byCategory[$match['category']] ??= [];
            if (! in_array($match['value'], $byCategory[$match['category']], true)) {
                $byCategory[$match['category']][] = $match['value'];
            }
        }

        $confirmedIndex = $this->matchOffset('~\b(a cause de|en raison de|principalement cause par|cause par|because of|due to|caused by)\b~', $normalized);
        $suspectedIndex = $this->matchOffset('~\b(semble lie a|pourrait etre|pourrait etre cause par|peut etre cause par|suspecte|probablement|appears related to|may be caused by)\b~', $normalized);
        $conceptMatches = array_values(array_filter($matches, fn (array $match): bool => $match['category'] === 'concept'));
        $confirmed = ($confirmedIndex < 0 || ($suspectedIndex >= 0 && $confirmedIndex > $suspectedIndex)) ? [] : $this->conceptsAfter($conceptMatches, $normalized, $confirmedIndex);
        $suspected = $suspectedIndex < 0 ? [] : $this->conceptsAfter($conceptMatches, $normalized, $suspectedIndex);

        return [
            'normalized' => $normalized,
            'matches' => $matches,
            'byCategory' => $byCategory,
            'concepts' => $byCategory['concept'] ?? [],
            'confirmedConcepts' => array_values(array_unique($confirmed)),
            'suspectedConcepts' => array_values(array_unique($suspected)),
            'unknownCause' => (bool) preg_match('~\b(ne sais pas pourquoi|cause inconnue|cause non etablie|unknown cause|cause is unknown|sans cause identifiee)\b~', $normalized),
            'hasNumbers' => (bool) preg_match('/\b\d+(?:[.,]\d+)?\b/', $normalized),
            'hasDocumentReference' => (bool) preg_match('~\b(document joint|fichier joint|piece jointe|ci joint|document fourni|document transmis|ce document|cet audit|attached|uploaded)\b~', $normalized),
        ];
    }

    private function matchOffset(string $pattern, string $text): int
    {
        return preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE) ? $match[0][1] : -1;
    }

    private function conceptsAfter(array $matches, string $text, int $offset): array
    {
        return array_values(array_map(
            fn (array $match): string => $match['value'],
            array_filter($matches, function (array $match) use ($text, $offset): bool {
                foreach ($match['terms'] as $term) {
                    if (strpos($text, $this->normalize($term)) > $offset) {
                        return true;
                    }
                }

                return false;
            })
        ));
    }
}
