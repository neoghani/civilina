<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EngineeringItp extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SUPERSEDED = 'superseded';

    protected $table = 'engineering_itps';

    protected $fillable = [
        'uuid', 'organization_id', 'project_id', 'code', 'title', 'revision', 'status',
        'scope_type', 'scope_ref', 'scope_label', 'reference_documents', 'notes',
        'created_by', 'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'reference_documents' => 'array',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $itp): void {
            $itp->uuid ??= (string) Str::uuid();
            $itp->status ??= self::STATUS_DRAFT;
            $itp->revision ??= 'DRAFT-01';
        });
    }

    public function rows(): HasMany
    {
        return $this->hasMany(EngineeringItpRow::class, 'engineering_itp_id')
            ->orderBy('sequence')->orderBy('id');
    }

    public function isDraft(): bool { return $this->status === self::STATUS_DRAFT; }
    public function isInReview(): bool { return $this->status === self::STATUS_IN_REVIEW; }
    public function isApproved(): bool { return $this->status === self::STATUS_APPROVED; }
}
