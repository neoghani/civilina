<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EngineeringQaEvidence extends Model
{
    protected $table = 'engineering_qa_evidences';

    protected $fillable = [
        'uuid', 'engineering_qa_inspection_id', 'organization_id', 'project_id', 'reference',
        'document_type', 'issued_at', 'revision', 'original_name', 'disk', 'path', 'mime_type',
        'size_bytes', 'sha256', 'status', 'uploaded_by', 'notes',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'size_bytes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $evidence): void {
            $evidence->uuid ??= (string) Str::uuid();
            $evidence->status ??= 'draft';
        });
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(EngineeringQaInspection::class, 'engineering_qa_inspection_id');
    }
}
