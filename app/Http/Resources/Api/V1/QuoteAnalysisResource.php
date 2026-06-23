<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quote_id' => $this->quote_id,
            'status' => $this->status,
            'summary' => $this->summary,
            'extracted_terms' => $this->extracted_terms,
            'hidden_costs' => $this->hidden_costs,
            'risk_notes' => $this->risk_notes,
            'confidence_score' => (float) $this->confidence_score,
            'model_name' => $this->model_name,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
