<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('country') && $this->country !== null) {
            $this->merge([
                'country' => strtoupper((string) $this->country),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    Vendor::STATUS_ACTIVE,
                    Vendor::STATUS_INACTIVE,
                    Vendor::STATUS_BLOCKED,
                ]),
            ],
            'country' => ['sometimes', 'required', 'string', 'size:2'],
            'search' => ['sometimes', 'required', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
