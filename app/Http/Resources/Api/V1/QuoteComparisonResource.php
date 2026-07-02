<?php

namespace App\Http\Resources\Api\V1;

use App\Models\QuoteComparison;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuoteComparison
 */
class QuoteComparisonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'purchase_request_id' => $this->purchase_request_id,
            'recommended_quote_id' => $this->recommended_quote_id,
            'recommended_vendor' => $this->recommendedQuote?->vendor?->name,
            'generated_by_user_id' => $this->generated_by_user_id,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'weights' => $this->weights,
            'quotes' => $this->quotes,
            'recommended_quote' => new QuoteResource($this->whenLoaded('recommendedQuote')),
            'purchase_request' => new PurchaseRequestResource($this->whenLoaded('purchaseRequest')),
            'generated_by' => new UserResource($this->whenLoaded('generatedBy')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
