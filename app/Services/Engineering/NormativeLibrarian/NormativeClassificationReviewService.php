<?php

namespace App\Services\Engineering\NormativeLibrarian;

use Illuminate\Support\Facades\DB;

class NormativeClassificationReviewService
{
    public function decide(int $classificationId, string $decision, string $note, string $actor): void
    {
        $decision = mb_strtoupper(trim($decision));
        $note = trim($note);
        $actor = trim($actor);

        if (! in_array($decision, ['CLASSIFIED', 'UNCLASSIFIED'], true)) {
            throw new \DomainException('Décision autorisée : CLASSIFIED ou UNCLASSIFIED.');
        }
        if ($note === '' || $actor === '') {
            throw new \DomainException('La note et le nom du relecteur sont obligatoires.');
        }

        DB::transaction(function () use ($classificationId, $decision, $note, $actor): void {
            $classification = DB::table('civilina_normative_classifications')
                ->where('id', $classificationId)
                ->first();

            if (! $classification) {
                throw new \RuntimeException('Classification introuvable.');
            }
            if ($classification->classification_status !== 'TO_REVIEW') {
                throw new \DomainException('Seule une classification TO_REVIEW peut recevoir une décision.');
            }
            if ($decision === 'CLASSIFIED' && trim((string) $classification->suggested_family) === '') {
                throw new \DomainException('Aucune famille proposée : utiliser UNCLASSIFIED ou compléter la revue humaine.');
            }

            DB::table('civilina_normative_classifications')->where('id', $classificationId)->update([
                'family' => $decision === 'CLASSIFIED' ? $classification->suggested_family : null,
                'classification_status' => $decision,
                'review_note' => $note,
                'reviewed_by_name' => $actor,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
