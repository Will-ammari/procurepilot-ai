<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Invoice;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'purchase_request_id' => $this->purchase_request_id,
            'vendor_id' => $this->vendor_id,

            'invoice_number' => $this->invoice_number,
            'invoice_date' => ApiDate::date($this->invoice_date),
            'due_date' => ApiDate::date($this->due_date),

            'subtotal' => (float) $this->subtotal,
            'vat_rate' => (float) $this->vat_rate,
            'vat_amount' => (float) $this->vat_amount,
            'total' => (float) $this->total,

            'currency' => $this->currency,
            'status' => $this->status,
            'notes' => $this->notes,
            'paid_at' => ApiDate::datetime($this->paid_at),

            'purchase_request' => new PurchaseRequestResource($this->whenLoaded('purchaseRequest')),
            'vendor' => new VendorResource($this->whenLoaded('vendor')),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
