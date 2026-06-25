<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'purchase_request_id' => [
                'required',
                'integer',
                Rule::exists('purchase_requests', 'id')
                    ->where('organization_id', $organizationId),
            ],

            'vendor_id' => [
                'required',
                'integer',
                Rule::exists('vendors', 'id')
                    ->where('organization_id', $organizationId),
            ],

            'invoice_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('invoices', 'invoice_number')
                    ->where('organization_id', $organizationId),
            ],

            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],

            'subtotal' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
