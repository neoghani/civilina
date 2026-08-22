<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Artifact extends Model
{
    use SoftDeletes;

    public const TYPE_ITP = 'itp';
    public const TYPE_WBS = 'wbs';
    public const TYPE_RISK_REGISTER = 'risk_register';
    public const TYPE_RACI = 'raci';
    public const TYPE_WORKFLOW = 'workflow';
    public const TYPE_DASHBOARD = 'dashboard';

    public const TYPES = [
        self::TYPE_ITP,
        self::TYPE_WBS,
        self::TYPE_RISK_REGISTER,
        self::TYPE_RACI,
        self::TYPE_WORKFLOW,
        self::TYPE_DASHBOARD,
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_IN_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_SUPERSEDED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'type',
        'title',
        'status',
        'created_by',
        'owner_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Artifact $artifact): void {
            if (! $artifact->public_id) {
                $artifact->public_id = (string) Str::ulid();
            }
        });
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ArtifactRevision::class)
            ->orderBy('revision_number');
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(
            ArtifactRevision::class,
            'current_revision_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'owner_id'
        );
    }
public function getRouteKeyName(): string
{
    return 'public_id';
}

}