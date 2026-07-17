<?php

namespace App\Models;

use App\Models\Concerns\HasSchoolLicensing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasSchoolLicensing;

    protected $table = 'schools';

    protected $fillable = [
        'name',
        'legal_name',
        'slug',
        'status',
        'timezone',
        'logo_path',
        'primary_color',
        'secondary_color',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address',
        'tax_id',
        'support_email',
        'whatsapp_number',
        'suspended_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function studentEnrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(UserDeviceToken::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function displayName(): string
    {
        return $this->legal_name ?: $this->name;
    }
}
