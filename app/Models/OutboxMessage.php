<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_FAILED = 'failed';

    public const EVENT_PURCHASE_REQUEST_SUBMITTED = 'purchase_request.submitted';

    protected $fillable = [
        'organization_id',
        'event_key',
        'event_type',
        'payload',
        'correlation_id',
        'status',
        'attempts',
        'next_attempt_at',
        'reserved_until',
        'lease_token',
        'dispatched_at',
        'last_error_code',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'next_attempt_at' => 'immutable_datetime',
            'reserved_until' => 'immutable_datetime',
            'dispatched_at' => 'immutable_datetime',
        ];
    }
}
