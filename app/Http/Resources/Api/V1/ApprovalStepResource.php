<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ApprovalStep;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApprovalStep
 */
class ApprovalStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'purchase_request_id' => $this->purchase_request_id,
            'sequence' => $this->sequence,
            'approval_role' => $this->approval_role,
            'approver_user_id' => $this->approver_user_id,
            'decided_by_user_id' => $this->decided_by_user_id,
            'status' => $this->status,
            'decision_comment' => $this->decision_comment,
            'decided_at' => ApiDate::datetime($this->decided_at),

            'approver' => new UserResource($this->whenLoaded('approver')),
            'decided_by' => new UserResource($this->whenLoaded('decidedBy')),
            'purchase_request' => new PurchaseRequestResource($this->whenLoaded('purchaseRequest')),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
