<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vendor
 */
class VendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'vat_id' => $this->vat_id,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'address' => $this->address,
            'country' => $this->country,
            'default_currency' => $this->default_currency,
            'status' => $this->status,
            'contacts' => VendorContactResource::collection($this->whenLoaded('contacts')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
