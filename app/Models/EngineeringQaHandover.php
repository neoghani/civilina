<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EngineeringQaHandover extends Model
{
    protected $table = 'engineering_qa_handovers';

    protected $fillable = [
        'uuid', 'organization_id', 'project_id', 'code', 'from_work_type', 'to_work_type',
        'scope_type', 'scope_ref', 'scope_label', 'status', 'requested_at', 'inspected_at',
        'client_authorized_at', 'accepted_at', 'client_reference', 'from_responsible',
        'to_responsible', 'client_representative', 'criteria', 'notes', 'created_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'inspected_at' => 'datetime',
        'client_authorized_at' => 'datetime',
        'accepted_at' => 'datetime',
        'criteria' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $handover): void {
            $handover->uuid ??= (string) Str::uuid();
            $handover->status ??= 'draft';
        });
    }
}
