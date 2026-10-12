<?php

namespace App\Services\Engineering\NormativeLibrarian;

use App\Models\NormativeLibrarianDocument;

class NormativeClassificationAuditor
{
    public function __construct(private NormativeSafeClassifier $classifier)
    {
    }

    /**
     * @return array<string,int>
     */
    public function audit(
        int $limit = 500,
        string $status = 'AUTO_CLASSIFIED',
        bool $apply = true
    ): array {
        $stats = [
            'seen'=>0,
            'safe'=>0,
            'medium_risk'=>0,
            'high_risk'=>0,
            'downgraded_review'=>0,
            'downgraded_unclassified'=>0,
            'validated_skipped'=>0,
        ];

        NormativeLibrarianDocument::query()
            ->where('classification_status', $status)
            ->where('document_kind', '!=', 'rejected')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (NormativeLibrarianDocument $document) use (&$stats, $apply) {
                $stats['seen']++;

                if ($document->classification_status === 'VALIDATED') {
                    $stats['validated_skipped']++;
                    return;
                }

                $safe = $this->classifier->classify($document);

                $sameDomain =
                    $safe['domain']
                    && $document->domain
                    && $safe['domain'] === $document->domain;

                $sameSubdomain =
                    $safe['subdomain']
                    && $document->subdomain
                    && $safe['subdomain'] === $document->subdomain;

                $risk = 100 - $safe['score'];

                if (! $sameDomain) {
                    $risk += 25;
                }

                if ($safe['conflicts']) {
                    $risk += 15;
                }

                $risk = min(100, max(0, $risk));

                $auditStatus = $risk >= 60
                    ? 'HIGH_RISK'
                    : ($risk >= 30 ? 'MEDIUM_RISK' : 'SAFE');

                if ($auditStatus === 'SAFE') {
                    $stats['safe']++;
                } elseif ($auditStatus === 'MEDIUM_RISK') {
                    $stats['medium_risk']++;
                } else {
                    $stats['high_risk']++;
                }

                $updates = [
                    'audit_status'=>$auditStatus,
                    'audit_risk'=>$risk,
                    'audit_reason'=>sprintf(
                        'Classification actuelle=%s/%s/%s. Réévaluation=%s/%s/%s (%d). %s',
                        $document->discipline ?: '—',
                        $document->domain ?: '—',
                        $document->subdomain ?: '—',
                        $safe['discipline'] ?: '—',
                        $safe['domain'] ?: '—',
                        $safe['subdomain'] ?: '—',
                        $safe['score'],
                        $safe['reason']
                    ),
                    'audited_at'=>now(),
                    'metadata'=>array_merge((array)$document->metadata, [
                        'classification_audit'=>[
                            'status'=>$auditStatus,
                            'risk'=>$risk,
                            'safe_classifier'=>$safe,
                            'at'=>now()->toIso8601String(),
                        ],
                    ]),
                ];

                /*
                 * On ne rétrograde automatiquement que les AUTO_CLASSIFIED.
                 * Les corrections humaines VALIDATED ne sont jamais touchées.
                 */
                if ($apply && $document->classification_status === 'AUTO_CLASSIFIED') {
                    if ($auditStatus === 'HIGH_RISK') {
                        $updates['classification_status'] = $safe['score'] >= 45
                            ? 'TO_REVIEW'
                            : 'UNCLASSIFIED';

                        if ($updates['classification_status'] === 'TO_REVIEW') {
                            $stats['downgraded_review']++;
                        } else {
                            $stats['downgraded_unclassified']++;
                        }
                    } elseif (
                        $auditStatus === 'MEDIUM_RISK'
                        && (! $sameDomain || ! $sameSubdomain)
                    ) {
                        $updates['classification_status'] = 'TO_REVIEW';
                        $stats['downgraded_review']++;
                    }
                }

                $document->forceFill($updates)->save();
            });

        return $stats;
    }
}
