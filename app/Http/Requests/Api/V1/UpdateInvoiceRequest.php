<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;
        $invoiceId = $this->route('invoice') instanceof Invoice
            ? $this->route('invoice')->id
            : null;

        return [
            'invoice_number' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('invoices', 'invoice_number')
                    ->where('organization_id', $organizationId)
                    ->ignore($invoiceId),
            ],

            'invoice_date' => ['sometimes', 'required', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:invoice_date'],

            'subtotal' => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'vat_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],

            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    Invoice::STATUS_RECEIVED,
                    Invoice::STATUS_APPROVED,
                    Invoice::STATUS_OVERDUE,
                    Invoice::STATUS_CANCELLED,
                ]),
            ],

            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:3000'],
        ];
    }
}
