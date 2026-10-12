<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StandardDocument extends Model
{
    protected $fillable = [
        'organization_id',
        'code',
        'title',
        'publisher',
        'jurisdiction',
        'discipline',
        'document_type',
        'language',
        'source_filename',
        'source_checksum',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function editions(): HasMany
    {
        return $this->hasMany(StandardEdition::class);
    }
}
