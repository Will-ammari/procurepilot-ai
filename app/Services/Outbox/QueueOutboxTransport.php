<?php

namespace App\Services\Outbox;

use App\Jobs\Procurement\RecordPurchaseRequestSubmitted;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use UnexpectedValueException;

class QueueOutboxTransport implements OutboxTransport
{
    public function deliver(OutboxMessage $message): void
    {
        // Explicit allow-list. Never hydrate arbitrary job classes from outbox data.
        if ($message->event_type !== OutboxMessage::EVENT_PURCHASE_REQUEST_SUBMITTED) {
            throw new UnexpectedValueException('Unsupported outbox event type.');
        }

        // Access the casted attribute through Eloquent: static schema inference
        // treats the raw JSON column as a string, while runtime casts it to an array.
        $payload = $message->getAttribute('payload');
        if (! is_array($payload)
            || ! is_int($payload['purchase_request_id'] ?? null)
            || ! is_int($payload['submitted_by_user_id'] ?? null)
            || ! is_string($payload['submitted_at'] ?? null)
            || strlen($payload['submitted_at']) > 40) {
            throw new UnexpectedValueException('Malformed outbox event payload.');
        }

        Bus::dispatch(new RecordPurchaseRequestSubmitted(
            $payload['purchase_request_id'],
            $payload['submitted_by_user_id'],
            $payload['submitted_at'],
            is_string($message->correlation_id) && Str::isUuid($message->correlation_id)
                ? $message->correlation_id
                : null,
        ));
    }
}
