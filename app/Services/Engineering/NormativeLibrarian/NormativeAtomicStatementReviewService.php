<?php

namespace App\Services\Engineering\NormativeLibrarian;

use DomainException;
use Illuminate\Support\Facades\DB;

class NormativeAtomicStatementReviewService
{
    public const DECISIONS = ['CANDIDATE', 'VALIDATED', 'REJECTED'];

    public function __construct(
        private readonly NormativeAtomicPublicationGateService $gate
    ) {
    }

    public function decide(
        int $statementId,
        string $decision,
        ?string $note = null,
        ?int $actorUserId = null,
        ?string $actorName = null
    ): void {
        $decision = mb_strtoupper(trim($decision));
        $note = trim((string) $note);

        if (! in_array($decision, self::DECISIONS, true)) {
            throw new DomainException('Decision de statement invalide.');
        }

        if ($decision === 'REJECTED' && $note === '') {
            throw new DomainException('Le motif du rejet est obligatoire.');
        }

        $statement = $this->gate->query()->where('a.id', $statementId)->first();
        if (! $statement) {
            throw new DomainException('Statement atomique introuvable.');
        }

        $now = now();
        $reviewedAt = $decision === 'CANDIDATE' ? null : $now;
        $provenanceHash = $decision === 'VALIDATED'
            ? $this->gate->makeProvenanceHash($statement)
            : null;

        DB::transaction(function () use (
            $statement,
            $decision,
            $note,
            $actorUserId,
            $actorName,
            $reviewedAt,
            $provenanceHash,
            $now
        ) {
            DB::table('civilina_normative_atomic_statements')
                ->where('id', $statement->id)
                ->update([
                    'review_status' => $decision,
                    'review_note' => $decision === 'CANDIDATE' ? null : ($note ?: null),
                    'reviewed_by_user_id' => $decision === 'CANDIDATE' ? null : $actorUserId,
                    'reviewed_by_name' => $decision === 'CANDIDATE' ? null : $actorName,
                    'reviewed_at' => $reviewedAt,
                    'provenance_hash' => $provenanceHash,
                    'extraction_state' => 'ACTIVE',
                    'superseded_at' => null,
                    'publication_eligible' => false,
                    'eligibility_reason' => 'Recalcul du Gate requis.',
                    'updated_at' => $now,
                ]);

            DB::table('civilina_normative_atomic_review_events')->insert([
                'atomic_statement_id' => $statement->id,
                'previous_status' => $statement->review_status,
                'decision' => $decision,
                'note' => $decision === 'CANDIDATE' ? null : ($note ?: null),
                'actor_user_id' => $actorUserId,
                'actor_name' => $actorName,
                'statement_hash' => $statement->statement_hash,
                'provenance_hash' => $provenanceHash,
                'decided_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        $this->gate->refreshEligibilityForDocument((int) $statement->deep_document_id);
    }
}
