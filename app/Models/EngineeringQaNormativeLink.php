<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineeringQaNormativeLink extends Model
{
    protected $table = 'engineering_qa_normative_links';

    protected $guarded = [];

    protected $casts = [
        'score' => 'decimal:2',
        'metadata' => 'array',
        'validated_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(EngineeringQaInspection::class, 'inspection_id');
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(EngineeringNormativeStandard::class, 'standard_id');
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(EngineeringNormativeEdition::class, 'edition_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(EngineeringNormativeSection::class, 'section_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(EngineeringNormativeRequirement::class, 'requirement_id');
    }

    /*
     * Compatibility with the v1 screen /qa-normative (audit E4).
     *
     * The table carries both schemas: v1 columns (engineering_qa_inspection_id,
     * source_label, clause_code, excerpt, confidence_score, match_reason…)
     * and v2 columns (inspection_id, standard_id, section_id, score, reason).
     * Values typed by an engineer (manual reference) or written by the v1
     * agent are stored in v1 columns and must win over the values derived
     * from the v2 relations; both inspection columns are kept in sync.
     */
    public const STATUS_SUGGESTED = 'proposed';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_REJECTED = 'rejected';

    /** @var array<string, bool> */
    private static array $columnCache = [];

    protected static function booted(): void
    {
        static::saving(function (EngineeringQaNormativeLink $link): void {
            $legacy = $link->getAttributes()['engineering_qa_inspection_id'] ?? null;
            $current = $link->getAttributes()['inspection_id'] ?? null;

            if ($current === null && $legacy !== null && self::hasColumn('inspection_id')) {
                $link->setAttribute('inspection_id', $legacy);
            }

            if ($legacy === null && $current !== null && self::hasColumn('engineering_qa_inspection_id')) {
                $link->setAttribute('engineering_qa_inspection_id', $current);
            }

            $legacyScore = $link->getAttributes()['confidence_score'] ?? null;
            if (($link->getAttributes()['score'] ?? null) === null && $legacyScore !== null && self::hasColumn('score')) {
                $link->setAttribute('score', $legacyScore);
            }

            $legacyReason = $link->getAttributes()['match_reason'] ?? null;
            if (($link->getAttributes()['reason'] ?? null) === null && $legacyReason !== null && self::hasColumn('reason')) {
                $link->setAttribute('reason', $legacyReason);
            }
        });
    }

    private static function hasColumn(string $column): bool
    {
        return self::$columnCache[$column] ??= \Illuminate\Support\Facades\Schema::hasColumn(
            (new self())->getTable(),
            $column
        );
    }

    private function stored(string $key): mixed
    {
        $value = $this->getAttributes()[$key] ?? null;

        return $value === '' ? null : $value;
    }

    public function getEngineeringQaInspectionIdAttribute(): ?int
    {
        $value = $this->stored('inspection_id') ?? $this->stored('engineering_qa_inspection_id');

        return $value !== null ? (int) $value : null;
    }

    public function getStandardSectionIdAttribute(): ?int
    {
        $value = $this->stored('section_id') ?? $this->stored('standard_section_id');

        return $value !== null ? (int) $value : null;
    }

    public function getStandardEditionIdAttribute(): ?int
    {
        $value = $this->stored('edition_id') ?? $this->stored('standard_edition_id');

        return $value !== null ? (int) $value : null;
    }

    public function getConfidenceScoreAttribute(): float
    {
        return (float) ($this->stored('score') ?? $this->stored('confidence_score') ?? 0);
    }

    public function getProjectProfileMatchAttribute(): int
    {
        return (int) ($this->stored('project_profile_match') ?? 0);
    }

    public function getMatchReasonAttribute(): ?string
    {
        return $this->stored('match_reason') ?? $this->reason;
    }

    public function getSourceReferenceAttribute(): ?string
    {
        return $this->stored('source_reference') ?? $this->standard?->canonical_code;
    }

    public function getSourceLabelAttribute(): ?string
    {
        return $this->stored('source_label') ?? $this->standard?->title;
    }

    public function getClauseCodeAttribute(): ?string
    {
        return $this->stored('clause_code') ?? $this->section?->section_code;
    }

    public function getExcerptAttribute(): ?string
    {
        return $this->stored('excerpt') ?? $this->requirement?->requirement_text;
    }
}
