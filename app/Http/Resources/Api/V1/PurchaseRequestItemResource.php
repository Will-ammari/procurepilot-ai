<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PurchaseRequestItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseRequestItem
 */
class PurchaseRequestItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_request_id' => $this->purchase_request_id,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'expected_unit_price' => $this->expected_unit_price !== null
                ? (float) $this->expected_unit_price
                : null,
            'category' => $this->category,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
