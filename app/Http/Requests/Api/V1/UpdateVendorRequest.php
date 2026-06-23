<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('country') && $this->country !== null) {
            $normalized['country'] = strtoupper((string) $this->country);
        }

        if ($this->has('default_currency') && $this->default_currency !== null) {
            $normalized['default_currency'] = strtoupper((string) $this->default_currency);
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        $vendor = $this->route('vendor');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('vendors', 'name')
                    ->where('organization_id', $this->user()->organization_id)
                    ->ignore($vendor?->id),
            ],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'vat_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'country' => ['sometimes', 'required', 'string', 'size:2'],
            'default_currency' => ['sometimes', 'required', 'string', 'size:3'],
            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    Vendor::STATUS_ACTIVE,
                    Vendor::STATUS_INACTIVE,
                    Vendor::STATUS_BLOCKED,
                ]),
            ],

            'contacts' => ['sometimes', 'array'],
            'contacts.*.name' => ['required_with:contacts', 'string', 'max:255'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.role' => ['nullable', 'string', 'max:100'],
        ];
    }
}
