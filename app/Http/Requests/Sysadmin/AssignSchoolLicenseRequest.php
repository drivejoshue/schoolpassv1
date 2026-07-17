<?php

namespace App\Http\Requests\Sysadmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignSchoolLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'superadmin'
            && $this->user()?->school_id === null;
    }

    public function rules(): array
    {
        return [
            'subscription_plan_id' => [
                'required',
                'integer',
                Rule::exists('subscription_plans', 'id')
                    ->where('status', 'active'),
            ],
            'status' => ['required', Rule::in(['trial', 'active'])],
            'billing_cycle' => [
                'required',
                Rule::in(['trial', 'monthly', 'annual', 'custom']),
            ],
            'starts_at' => ['required', 'date'],
            'expires_at' => [
                'nullable',
                'date',
                'after_or_equal:starts_at',
            ],
            'trial_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'contract_price' => ['nullable', 'numeric', 'min:0'],
            'student_limit' => ['nullable', 'integer', 'min:0'],
            'device_limit' => ['nullable', 'integer', 'min:0'],
            'staff_limit' => ['nullable', 'integer', 'min:0'],
            'campus_limit' => ['nullable', 'integer', 'min:0'],
            'auto_renew' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'auto_renew' => $this->boolean('auto_renew'),
        ]);

        foreach ([
            'contract_price',
            'student_limit',
            'device_limit',
            'staff_limit',
            'campus_limit',
            'expires_at',
        ] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->input('status') === 'trial'
                    && $this->input('billing_cycle') !== 'trial'
                ) {
                    $validator->errors()->add(
                        'billing_cycle',
                        'Una licencia de prueba debe usar el ciclo prueba.'
                    );
                }

                if (
                    $this->input('status') === 'active'
                    && $this->input('billing_cycle') === 'trial'
                ) {
                    $validator->errors()->add(
                        'billing_cycle',
                        'Una licencia activa no puede usar el ciclo prueba.'
                    );
                }

                if (
                    $this->input('status') === 'trial'
                    && ! $this->filled('trial_days')
                ) {
                    $validator->errors()->add(
                        'trial_days',
                        'Indica la duración del periodo de prueba.'
                    );
                }

                if (
                    $this->input('billing_cycle') === 'custom'
                    && ! $this->filled('expires_at')
                ) {
                    $validator->errors()->add(
                        'expires_at',
                        'Indica el vencimiento del contrato personalizado.'
                    );
                }
            },
        ];
    }
}
