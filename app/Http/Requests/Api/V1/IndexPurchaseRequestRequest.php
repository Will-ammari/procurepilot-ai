<?php

namespace App\Http\Requests\Api\V1;

use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPurchaseRequestRequest extends FormRequest
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
            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    PurchaseRequest::STATUS_DRAFT,
                    PurchaseRequest::STATUS_SUBMITTED,
                    PurchaseRequest::STATUS_SOURCING,
                    PurchaseRequest::STATUS_QUOTES_RECEIVED,
                    PurchaseRequest::STATUS_PENDING_APPROVAL,
                    PurchaseRequest::STATUS_APPROVED,
                    PurchaseRequest::STATUS_REJECTED,
                    PurchaseRequest::STATUS_ORDERED,
                    PurchaseRequest::STATUS_INVOICED,
                    PurchaseRequest::STATUS_PAID,
                    PurchaseRequest::STATUS_CLOSED,
                ]),
            ],
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
            'department_id' => [
                'sometimes',
                'integer',
                Rule::exists('departments', 'id')
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'requester_id' => [
                'sometimes',
                'integer',
                Rule::exists('users', 'id')
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'search' => ['sometimes', 'required', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
