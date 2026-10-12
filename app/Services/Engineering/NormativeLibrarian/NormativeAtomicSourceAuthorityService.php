<?php

namespace App\Services\Engineering\NormativeLibrarian;

class NormativeAtomicSourceAuthorityService
{
    public function detect(?string $editionKey, string $openingText): array
    {
        $opening = mb_strtolower(mb_substr($openingText, 0, 6000));
        $edition = mb_strtoupper(trim((string) $editionKey));

        if ($edition === ''
            || str_contains($edition, 'ANNEE_INCONNUE')
            || str_contains($edition, 'ANNÉE_INCONNUE')
            || str_contains($edition, 'UNKNOWN')
            || str_contains($edition, 'INCONNUE')) {
            return [
                'authority'=>'BLOCKED_EDITION',
                'reason'=>'Édition normative non déterminée.',
            ];
        }

        $secondarySignals = [
            'etude de la norme',
            'étude de la norme',
            'résumé de la norme',
            'resume de la norme',
            'synthèse de la norme',
            'synthese de la norme',
            'guide d’application',
            "guide d'application",
            'cours',
            'support de formation',
            'présentation',
            'presentation',
            'fiche pratique',
            'mémo',
            'memo',
            'commentaire de la norme',
        ];

        foreach ($secondarySignals as $signal) {
            if (str_contains($opening, $signal)) {
                return [
                    'authority'=>'SECONDARY_DETECTED',
                    'reason'=>'Signal de document secondaire : '.$signal,
                ];
            }
        }

        $primarySignals = [
            'european standard',
            'british standard',
            'norme française',
            'norme europeenne',
            'norme européenne',
            'international standard',
            'iso ',
            'en ',
            'nf ',
        ];

        foreach ($primarySignals as $signal) {
            if (str_contains($opening, $signal)) {
                return [
                    'authority'=>'PRIMARY_CANDIDATE',
                    'reason'=>'Source paraissant être un texte normatif primaire ; validation humaine de source requise.',
                ];
            }
        }

        return [
            'authority'=>'UNVERIFIED',
            'reason'=>'Autorité de la source non démontrée automatiquement.',
        ];
    }
}
