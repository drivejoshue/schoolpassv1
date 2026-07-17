<?php

namespace App\Http\Requests\Sysadmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolLicenseLimitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'superadmin'
            && $this->user()?->school_id === null;
    }

    public function rules(): array
    {
        return [
            'student_limit' => ['nullable', 'integer', 'min:0'],
            'device_limit' => ['nullable', 'integer', 'min:0'],
            'staff_limit' => ['nullable', 'integer', 'min:0'],
            'campus_limit' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'student_limit',
            'device_limit',
            'staff_limit',
            'campus_limit',
        ] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }
}
