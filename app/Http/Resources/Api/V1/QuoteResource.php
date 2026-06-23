<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'purchase_request_id' => $this->purchase_request_id,
            'vendor_id' => $this->vendor_id,
            'total_amount' => (float) $this->total_amount,
            'currency' => $this->currency,
            'delivery_days' => $this->delivery_days,
            'payment_terms' => $this->payment_terms,
            'warranty_months' => $this->warranty_months,
            'valid_until' => $this->valid_until?->toDateString(),
            'notes' => $this->notes,
            'status' => $this->status,

            'purchase_request' => new PurchaseRequestResource($this->whenLoaded('purchaseRequest')),
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
            'items' => QuoteItemResource::collection($this->whenLoaded('items')),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
