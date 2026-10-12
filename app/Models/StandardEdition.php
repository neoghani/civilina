<?php

namespace App\Models;

use App\Enums\StandardEditionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StandardEdition extends Model
{
    protected $fillable = [
        'standard_document_id',
        'edition_code',
        'publication_date',
        'effective_from',
        'withdrawn_at',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'effective_from' => 'date',
            'withdrawn_at' => 'date',
            'status' => StandardEditionStatus::class,
            'metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(
            StandardDocument::class,
            'standard_document_id'
        );
    }

    public function sections(): HasMany
    {
        return $this->hasMany(StandardSection::class);
    }

    public function projectStandards(): HasMany
    {
        return $this->hasMany(ProjectStandard::class);
    }
}
