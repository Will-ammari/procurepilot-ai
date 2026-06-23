<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'department_id' => $this->department_id,
            'requester_id' => $this->requester_id,
            'title' => $this->title,
            'description' => $this->description,
            'needed_by_date' => $this->needed_by_date?->toDateString(),
            'estimated_budget' => $this->estimated_budget !== null
                ? (float) $this->estimated_budget
                : null,
            'currency' => $this->currency,
            'priority' => $this->priority,
            'status' => $this->status,
            'approved_quote_id' => $this->approved_quote_id,

            'department' => new DepartmentResource($this->whenLoaded('department')),
            'requester' => $this->whenLoaded('requester', function (): array {
                return [
                    'id' => $this->requester->id,
                    'name' => $this->requester->name,
                    'email' => $this->requester->email,
                    'role' => $this->requester->role,
                ];
            }),
            'items' => PurchaseRequestItemResource::collection($this->whenLoaded('items')),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
