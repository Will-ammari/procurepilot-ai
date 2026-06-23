<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuoteRequest extends FormRequest
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
        $quote = $this->route('quote');

        return [
            'total_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'delivery_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3650'],
            'payment_terms' => ['sometimes', 'nullable', 'string', 'max:255'],
            'warranty_months' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:240'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
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

            'items' => ['sometimes', 'array'],
            'items.*.purchase_request_item_id' => [
                'nullable',
                'integer',
                Rule::exists('purchase_request_items', 'id')
                    ->where('purchase_request_id', $quote?->purchase_request_id),
            ],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.total_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
