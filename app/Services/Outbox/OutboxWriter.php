<?php

namespace App\Services\Outbox;

use App\Models\OutboxMessage;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Writes a durable event in the SAME database transaction as the business state.
 * Never dispatches to a queue from here.
 */
class OutboxWriter
{
    public function purchaseRequestSubmitted(PurchaseRequest $purchaseRequest, User $user): OutboxMessage
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Outbox events must be recorded within the aggregate transaction.');
        }

        $requestId = request()->attributes->get('request_id');
        $correlationId = is_string($requestId) && Str::isUuid($requestId) ? $requestId : null;

        return OutboxMessage::query()->createOrFirst(
            ['event_key' => 'purchase_request.submitted:'.$purchaseRequest->id],
            [
                'organization_id' => $purchaseRequest->organization_id,
                'event_type' => OutboxMessage::EVENT_PURCHASE_REQUEST_SUBMITTED,
                'payload' => [
                    'purchase_request_id' => $purchaseRequest->id,
                    'submitted_by_user_id' => $user->id,
                    'submitted_at' => $purchaseRequest->updated_at?->toISOString() ?? now()->toISOString(),
                ],
                'correlation_id' => $correlationId,
                'status' => OutboxMessage::STATUS_PENDING,
                'next_attempt_at' => now(),
            ]
        );
    }
}
