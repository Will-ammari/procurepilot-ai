<?php

namespace App\Services\Outbox;

use App\Models\OutboxMessage;

interface OutboxTransport
{
    /** May throw: the caller must retain the event for retry. */
    public function deliver(OutboxMessage $message): void;
}
