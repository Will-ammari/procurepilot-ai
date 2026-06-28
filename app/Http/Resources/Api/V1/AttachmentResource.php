<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'uploaded_by_user_id' => $this->uploaded_by_user_id,
            'attachable_type' => $this->attachable_type,
            'attachable_id' => $this->attachable_id,
            'original_name' => $this->original_name,
            'stored_name' => $this->stored_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'download_url' => route('attachments.download', $this->id, false),

            'uploaded_by' => new UserResource($this->whenLoaded('uploadedBy')),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
