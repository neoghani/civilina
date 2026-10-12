<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineeringItpRow extends Model
{
    public const CHECKPOINT_HOLD = 'HP';
    public const CHECKPOINT_WITNESS = 'WP';
    public const CHECKPOINT_SURVEILLANCE = 'SURVEILLANCE';

    protected $table = 'engineering_itp_rows';

    protected $fillable = [
        'engineering_itp_id', 'sequence', 'code', 'inspection_test', 'method', 'frequency',
        'acceptance_criteria', 'checkpoint_type', 'responsibility_execute',
        'responsibility_inspect', 'responsibility_approve', 'responsibility_accept',
        'record_required', 'source_reference', 'notes',
    ];

    protected $casts = ['sequence' => 'integer'];

    public function itp(): BelongsTo
    {
        return $this->belongsTo(EngineeringItp::class, 'engineering_itp_id');
    }
}
