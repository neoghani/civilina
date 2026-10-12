<?php

namespace App\Services\Engineering\NormativeLibrarian;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NormativeQaRegistryMatchReviewService
{
    public function review(int $matchId, string $decision, string $note, string $actor): void
    {
        $decision = mb_strtoupper(trim($decision));
        $note = trim($note);
        $actor = trim($actor);

        if (! in_array($decision, ['VALIDATED', 'REJECTED'], true)) {
            throw new \DomainException('Décision autorisée : VALIDATED ou REJECTED.');
        }
        if ($note === '' || $actor === '') {
            throw new \DomainException('La note et l acteur humain sont obligatoires.');
        }
        if (! Schema::hasTable('civilina_qa_normative_match_review_events_v3')) {
            throw new \RuntimeException('Table de revue V3 absente.');
        }

        DB::transaction(function () use ($matchId, $decision, $note, $actor): void {
            $match = DB::table('civilina_qa_normative_matches_v3')->where('id', $matchId)->first();
            if (! $match) {
                throw new \RuntimeException('Résultat V3 introuvable.');
            }
            if ($match->result_status !== 'PROPOSED') {
                throw new \DomainException('Seul un résultat PROPOSED peut recevoir une décision humaine.');
            }

            DB::table('civilina_qa_normative_matches_v3')->where('id', $matchId)->update([
                'review_status' => $decision,
                'review_note' => $note,
                'reviewed_by' => $actor,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('civilina_qa_normative_match_review_events_v3')->insert([
                'match_id' => $matchId,
                'previous_status' => $match->review_status,
                'decision' => $decision,
                'note' => $note,
                'actor' => $actor,
                'match_snapshot' => json_encode($match, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'decided_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
