<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_type' => [
                'sometimes',
                'string',
                Rule::in(['purchase_request', 'quote', 'invoice']),
            ],
            'subject_id' => ['sometimes', 'integer', 'min:1'],
            'mime_type' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
