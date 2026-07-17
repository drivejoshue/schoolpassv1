<?php

namespace App\Http\Requests\Sysadmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RenewSchoolLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'superadmin'
            && $this->user()?->school_id === null;
    }

    public function rules(): array
    {
        return [
            'billing_cycle' => [
                'required',
                Rule::in(['monthly', 'annual', 'custom']),
            ],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'contract_price' => ['required', 'numeric', 'min:0'],
            'auto_renew' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'auto_renew' => $this->boolean('auto_renew'),
        ]);

        if ($this->input('expires_at') === '') {
            $this->merge(['expires_at' => null]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->input('billing_cycle') === 'custom'
                    && ! $this->filled('expires_at')
                ) {
                    $validator->errors()->add(
                        'expires_at',
                        'Indica el vencimiento de la renovación personalizada.'
                    );
                }
            },
        ];
    }
}
