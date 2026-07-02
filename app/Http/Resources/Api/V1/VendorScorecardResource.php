<?php

namespace App\Http\Resources\Api\V1;

use App\Models\VendorScorecard;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VendorScorecard
 */
class VendorScorecardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'vendor_id' => $this->vendor_id,

            'total_quotes' => $this->total_quotes,
            'accepted_quotes' => $this->accepted_quotes,
            'win_rate' => (float) $this->win_rate,

            'average_delivery_days' => $this->average_delivery_days !== null
                ? (float) $this->average_delivery_days
                : null,

            'total_invoices' => $this->total_invoices,
            'paid_invoices' => $this->paid_invoices,
            'invoice_issue_count' => $this->invoice_issue_count,

            'total_invoiced_amount' => (float) $this->total_invoiced_amount,
            'currency' => $this->currency,

            'overall_score' => (float) $this->overall_score,
            'calculated_at' => ApiDate::datetime($this->calculated_at),

            'vendor' => new VendorResource($this->whenLoaded('vendor')),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
