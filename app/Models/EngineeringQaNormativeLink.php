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
     * Compatibilité lecture avec l'ancienne vue /qa-normative.
     * Aucun ancien champ n'est recréé en base : les alias sont calculés.
     */
    public function getEngineeringQaInspectionIdAttribute(): ?int
    {
        return $this->inspection_id ? (int) $this->inspection_id : null;
    }

    public function getStandardSectionIdAttribute(): ?int
    {
        return $this->section_id ? (int) $this->section_id : null;
    }

    public function getStandardEditionIdAttribute(): ?int
    {
        return $this->edition_id ? (int) $this->edition_id : null;
    }

    public function getConfidenceScoreAttribute(): float
    {
        return (float) ($this->score ?? 0);
    }

    public function getProjectProfileMatchAttribute(): int
    {
        return 0;
    }

    public function getMatchReasonAttribute(): ?string
    {
        return $this->reason;
    }

    public function getSourceReferenceAttribute(): ?string
    {
        return $this->standard?->canonical_code;
    }

    public function getSourceLabelAttribute(): ?string
    {
        return $this->standard?->title;
    }

    public function getClauseCodeAttribute(): ?string
    {
        return $this->section?->section_code;
    }

    public function getExcerptAttribute(): ?string
    {
        return $this->requirement?->requirement_text;
    }
}
