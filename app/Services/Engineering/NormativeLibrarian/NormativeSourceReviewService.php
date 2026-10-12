<?php

namespace App\Services\Engineering\NormativeLibrarian;

use DomainException;
use Illuminate\Support\Facades\DB;

class NormativeSourceReviewService
{
    public const DECISIONS = ['PRIMARY_VALIDATED', 'SECONDARY_REJECTED', 'NEEDS_REVIEW'];

    public function __construct(
        private readonly NormativeAtomicPublicationGateService $gate
    ) {
    }

    public function decide(
        int $deepDocumentId,
        string $decision,
        string $note,
        ?int $actorUserId = null,
        ?string $actorName = null
    ): void {
        $decision = mb_strtoupper(trim($decision));
        $note = trim($note);

        if (! in_array($decision, self::DECISIONS, true)) {
            throw new DomainException('Decision de source invalide.');
        }

        if ($note === '') {
            throw new DomainException('Une note de decision humaine est obligatoire.');
        }

        $source = DB::table('civilina_normative_atomic_source_reviews')
            ->where('deep_document_id', $deepDocumentId)
            ->first();

        if (! $source) {
            throw new DomainException('Document source introuvable dans la file de revue.');
        }

        if ($decision === 'PRIMARY_VALIDATED') {
            if ($source->detected_authority !== 'PRIMARY_CANDIDATE') {
                throw new DomainException('Seule une source PRIMARY_CANDIDATE peut etre validee primaire.');
            }
            if (! $this->gate->hasKnownEdition($source->edition_key)) {
                throw new DomainException('Edition inconnue : PRIMARY_VALIDATED est interdit.');
            }
        }

        $now = now();

        DB::transaction(function () use (
            $source,
            $decision,
            $note,
            $actorUserId,
            $actorName,
            $now
        ) {
            DB::table('civilina_normative_atomic_source_reviews')
                ->where('id', $source->id)
                ->update([
                    'review_status' => $decision,
                    'review_note' => $note,
                    'reviewed_by_user_id' => $actorUserId,
                    'reviewed_by_name' => $actorName,
                    'reviewed_at' => $now,
                    'updated_at' => $now,
                ]);

            DB::table('civilina_normative_source_review_events')->insert([
                'source_review_id' => $source->id,
                'deep_document_id' => $source->deep_document_id,
                'previous_status' => $source->review_status,
                'decision' => $decision,
                'note' => $note,
                'actor_user_id' => $actorUserId,
                'actor_name' => $actorName,
                'source_snapshot' => json_encode([
                    'reference_code' => $source->reference_code,
                    'edition_key' => $source->edition_key,
                    'detected_authority' => $source->detected_authority,
                    'detected_reason' => $source->detected_reason,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'decided_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        $this->gate->refreshEligibilityForDocument($deepDocumentId);
    }
}
