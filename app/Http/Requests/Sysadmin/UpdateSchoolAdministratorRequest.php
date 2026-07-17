<?php

namespace App\Http\Requests\Sysadmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolAdministratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'superadmin'
            && $this->user()?->school_id === null;
    }

    public function rules(): array
    {
        $administrator = $this->route('administrator');
        $administratorId = is_object($administrator)
            ? $administrator->id
            : $administrator;

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => [
                'required',
                'email',
                'max:180',
                Rule::unique('users', 'email')
                    ->ignore($administratorId),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => [
                'required',
                Rule::in(['director', 'school_admin']),
            ],
            'status' => [
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ];
    }
}
