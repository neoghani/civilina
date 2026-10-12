<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineeringQaRegisterEntry extends Model
{
    protected $table = 'engineering_qa_register_entries';

    protected $fillable = [
        'engineering_qa_inspection_id', 'organization_id', 'project_id', 'work_type',
        'register_type', 'reference', 'entry_date', 'scope_label', 'payload', 'quality_status',
        'source_reference', 'notes', 'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'payload' => 'array',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(EngineeringQaInspection::class, 'engineering_qa_inspection_id');
    }
}
