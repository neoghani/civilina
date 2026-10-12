<?php

namespace App\Services\Engineering;

use App\Models\EngineeringItp;
use App\Models\EngineeringItpRow;

class EngineeringItpReadinessService
{
    /**
     * Aucune exigence technique n'est inventée ici.
     * La readiness repose seulement sur les données explicitement enregistrées.
     *
     * @return array{ready: bool, blockers: array<int,string>, row_count: int}
     */
    public function evaluate(EngineeringItp $itp): array
    {
        $rows = $itp->relationLoaded('rows') ? $itp->rows : $itp->rows()->get();
        $blockers = [];

        if (blank($itp->code)) $blockers[] = 'Code ITP manquant.';
        if (blank($itp->title)) $blockers[] = 'Titre ITP manquant.';
        if (blank($itp->scope_label)) $blockers[] = 'Périmètre ITP non défini.';
        if (! is_array($itp->reference_documents) || count($itp->reference_documents) === 0) {
            $blockers[] = 'Au moins une référence applicable doit être renseignée.';
        }
        if ($rows->isEmpty()) $blockers[] = 'Au moins une ligne de contrôle / essai est requise.';

        $rows->each(function (EngineeringItpRow $row, int $index) use (&$blockers): void {
            $label = $row->code ?: 'ligne '.($index + 1);
            if (blank($row->inspection_test)) $blockers[] = "$label: contrôle / essai manquant.";
            if (blank($row->acceptance_criteria)) $blockers[] = "$label: critère d’acceptation manquant.";
            if (blank($row->checkpoint_type)) $blockers[] = "$label: point qualité HP/WP/Surveillance manquant.";
            if (blank($row->responsibility_execute)) $blockers[] = "$label: responsable d’exécution manquant.";
            if (blank($row->responsibility_inspect)) $blockers[] = "$label: responsable d’inspection manquant.";
            if (blank($row->record_required)) $blockers[] = "$label: enregistrement / preuve attendu manquant.";
        });

        return [
            'ready' => count($blockers) === 0,
            'blockers' => array_values(array_unique($blockers)),
            'row_count' => $rows->count(),
        ];
    }
}
