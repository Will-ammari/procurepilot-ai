<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency') && $this->currency !== null) {
            $this->merge([
                'currency' => strtoupper((string) $this->currency),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'vendor_id' => [
                'sometimes',
                'integer',
                Rule::exists('vendors', 'id')
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    Quote::STATUS_DRAFT,
                    Quote::STATUS_RECEIVED,
                    Quote::STATUS_ANALYSIS_PENDING,
                    Quote::STATUS_ANALYZED,
                ]),
            ],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
