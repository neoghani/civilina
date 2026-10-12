<?php

namespace App\Models;

use App\Enums\DesignBasisCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignBasisItem extends Model
{
    protected $fillable = [
        'design_basis_revision_id',
        'category',
        'key',
        'label',
        'value_text',
        'value_number',
        'value_json',
        'unit',
        'source_type',
        'source_reference',
        'is_assumption',
    ];

    protected function casts(): array
    {
        return [
            'category' => DesignBasisCategory::class,
            'value_number' => 'decimal:6',
            'value_json' => 'array',
            'is_assumption' => 'boolean',
        ];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(
            DesignBasisRevision::class,
            'design_basis_revision_id'
        );
    }
}
