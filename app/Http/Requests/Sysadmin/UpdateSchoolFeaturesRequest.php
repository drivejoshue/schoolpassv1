<?php

namespace App\Http\Requests\Sysadmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolFeaturesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'superadmin'
            && $this->user()?->school_id === null;
    }

    public function rules(): array
    {
        return [
            'features' => ['required', 'array'],
            'features.*' => [
                'required',
                Rule::in(['inherit', 'enabled', 'disabled']),
            ],
        ];
    }
}
