<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorRequest extends FormRequest
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
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vendors', 'name')
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'vat_id' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'country' => ['nullable', 'string', 'size:2'],
            'default_currency' => ['nullable', 'string', 'size:3'],
            'status' => [
                'nullable',
                Rule::in([
                    Vendor::STATUS_ACTIVE,
                    Vendor::STATUS_INACTIVE,
                    Vendor::STATUS_BLOCKED,
                ]),
            ],

            'contacts' => ['nullable', 'array'],
            'contacts.*.name' => ['required_with:contacts', 'string', 'max:255'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.role' => ['nullable', 'string', 'max:100'],
        ];
    }
}
