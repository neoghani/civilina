<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngineeringStandardInboxFile extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_MATCHED = 'matched';
    public const STATUS_INDEXED = 'indexed';
    public const STATUS_SCAN_ERROR = 'scan_error';

    protected $table = 'engineering_standard_inbox_files';

    protected $fillable = [
        'relative_path',
        'original_name',
        'extension',
        'sha256',
        'size_bytes',
        'status',
        'standard_document_id',
        'detected_reference',
        'notes',
        'last_scanned_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'last_scanned_at' => 'datetime',
    ];
}
