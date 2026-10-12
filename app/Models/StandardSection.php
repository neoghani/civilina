<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StandardSection extends Model
{
    protected $fillable = [
        'standard_edition_id',
        'parent_id',
        'clause_reference',
        'title',
        'page_start',
        'page_end',
        'source_locator',
        'content_hash',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'page_start' => 'integer',
            'page_end' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(
            StandardEdition::class,
            'standard_edition_id'
        );
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'parent_id'
        );
    }

    public function children(): HasMany
    {
        return $this->hasMany(
            self::class,
            'parent_id'
        )->orderBy('sort_order')
            ->orderBy('id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(NormativeRequirement::class);
    }
}
