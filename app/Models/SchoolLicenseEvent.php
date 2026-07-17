<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolLicenseEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'school_id',
        'school_license_id',
        'event_type',
        'previous_status',
        'new_status',
        'metadata_json',
        'performed_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(SchoolLicense::class, 'school_license_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
