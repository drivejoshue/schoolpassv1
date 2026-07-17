<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    protected $fillable = [
        'school_id',
        'student_id',
        'academic_cycle_id',
        'school_group_id',
        'campus_id',
        'previous_enrollment_id',
        'status',
        'enrollment_type',
        'enrolled_on',
        'completed_on',
        'withdrawn_on',
        'withdrawal_reason',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'enrolled_on' => 'date',
        'completed_on' => 'date',
        'withdrawn_on' => 'date',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(
            School::class
        );
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(
            Student::class
        );
    }

    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(
            AcademicCycle::class
        );
    }

    public function schoolGroup(): BelongsTo
    {
        return $this->belongsTo(
            SchoolGroup::class
        );
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(
            Campus::class
        );
    }

    public function previousEnrollment(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'previous_enrollment_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }
}