<?php

namespace App\Http\Requests\Api\V1;

use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseRequestRequest extends FormRequest
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
            'department_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('departments', 'id')
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'needed_by_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
            'estimated_budget' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'priority' => [
                'sometimes',
                'required',
                Rule::in([
                    PurchaseRequest::PRIORITY_LOW,
                    PurchaseRequest::PRIORITY_NORMAL,
                    PurchaseRequest::PRIORITY_HIGH,
                    PurchaseRequest::PRIORITY_URGENT,
                ]),
            ],

            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.expected_unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.category' => ['nullable', 'string', 'max:255'],
        ];
    }
}
