<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends FormRequest
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
        $purchaseRequest = $this->route('purchaseRequest');

        return [
            'vendor_id' => [
                'required',
                'integer',
                Rule::exists('vendors', 'id')
                    ->where('organization_id', $this->user()->organization_id),
                Rule::unique('quotes', 'vendor_id')
                    ->where('purchase_request_id', $purchaseRequest?->id),
            ],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'delivery_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:240'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => [
                'nullable',
                Rule::in([
                    Quote::STATUS_DRAFT,
                    Quote::STATUS_RECEIVED,
                ]),
            ],

            'items' => ['nullable', 'array'],
            'items.*.purchase_request_item_id' => [
                'nullable',
                'integer',
                Rule::exists('purchase_request_items', 'id')
                    ->where('purchase_request_id', $purchaseRequest?->id),
            ],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.total_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
