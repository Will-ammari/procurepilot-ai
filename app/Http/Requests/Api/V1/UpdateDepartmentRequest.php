<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && $this->code !== null) {
            $this->merge([
                'code' => strtoupper((string) $this->code),
            ]);
        }
    }

    public function rules(): array
    {
        $department = $this->route('department');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->where('organization_id', $this->user()->organization_id)
                    ->ignore($department?->id),
            ],
            'code' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
