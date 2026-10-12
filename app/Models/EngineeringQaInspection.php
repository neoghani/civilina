<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EngineeringQaInspection extends Model
{
    public const STATUS_PLANNED = 'planned';
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_CONFORMING = 'conforming';
    public const STATUS_NONCONFORMING = 'nonconforming';
    public const STATUS_CONDITIONAL = 'conditional';
    public const STATUS_CANCELLED = 'cancelled';

    public const AUTH_NONE = 'none';
    public const AUTH_REQUESTED = 'requested';
    public const AUTH_GRANTED = 'granted';
    public const AUTH_REJECTED = 'rejected';

    protected $table = 'engineering_qa_inspections';

    protected $fillable = [
        'uuid', 'organization_id', 'project_id', 'engineering_itp_id', 'engineering_itp_row_id',
        'template_key', 'work_type', 'inspection_code', 'title', 'record_type', 'register_type',
        'checkpoint_type', 'scope_type', 'scope_ref', 'scope_label', 'status',
        'authorization_status', 'requested_at', 'planned_at', 'inspected_at', 'received_at',
        'client_authorized_at', 'client_reference', 'inspector', 'client_representative',
        'acceptance_criteria', 'requirement_source', 'notes', 'created_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'planned_at' => 'datetime',
        'inspected_at' => 'datetime',
        'received_at' => 'datetime',
        'client_authorized_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $inspection): void {
            $inspection->uuid ??= (string) Str::uuid();
            $inspection->status ??= self::STATUS_PLANNED;
            $inspection->authorization_status ??= self::AUTH_NONE;
        });
    }

    public function itp(): BelongsTo
    {
        return $this->belongsTo(EngineeringItp::class, 'engineering_itp_id');
    }

    public function itpRow(): BelongsTo
    {
        return $this->belongsTo(EngineeringItpRow::class, 'engineering_itp_row_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(EngineeringQaEvidence::class, 'engineering_qa_inspection_id')
            ->orderByDesc('id');
    }

    public function registerEntries(): HasMany
    {
        return $this->hasMany(EngineeringQaRegisterEntry::class, 'engineering_qa_inspection_id')
            ->orderByDesc('entry_date')->orderByDesc('id');
    }
    public function normativeLinks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\EngineeringQaNormativeLink::class, 'inspection_id')
            ->with(['standard', 'edition', 'section', 'requirement'])
            ->orderByDesc('score')
            ->orderByDesc('id');
    }
}
