<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NormativeLibrarianDocument extends Model
{
    protected $table = 'civilina_normative_librarian_documents';
    protected $guarded = [];

    protected $casts = [
        'ics_codes' => 'array',
        'metadata' => 'array',
        'confidence' => 'decimal:2',
        'published_at' => 'datetime',
        'source_modified_at' => 'datetime',
        'last_scanned_at' => 'datetime',
    ];

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }
}
