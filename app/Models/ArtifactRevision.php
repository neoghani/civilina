<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class ArtifactRevision extends Model
{
    protected $fillable = [
        'revision_number',
        'revision_label',
        'status',
        'payload',
        'provenance',
        'change_summary',
        'source',
        'checksum',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'provenance' => 'array',
            'revision_number' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ArtifactRevision $revision): void {
            if (! $revision->public_id) {
                $revision->public_id = (string) Str::ulid();
            }
        });

        /*
         * Une révision existante est immuable.
         * Toute modification métier produit une nouvelle révision.
         */
        static::updating(function (): void {
            throw new LogicException(
                'Artifact revisions are immutable. Create a new revision instead.'
            );
        });
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(Artifact::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}