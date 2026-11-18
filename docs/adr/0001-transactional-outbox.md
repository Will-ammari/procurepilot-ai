# ADR-0001 — Durable procurement event publication

**Status:** Accepted for Phase 3 initial rollout. **Scope:** purchase-request submission audit event only.

## Context

The Phase 1 `PurchaseRequestService::submit` updated the request inside a DB transaction and called `RecordPurchaseRequestSubmitted::dispatch(...)->afterCommit()`. `afterCommit()` prevents premature dispatch but does **not** make publication durable. A PHP process can stop after commit and before enqueue, or Redis can be unavailable. The request is then submitted but the async audit job can be lost.

## Decision

* Write one `outbox_messages` row in the **same MySQL transaction** that changes the request status. The globally unique `event_key` is `purchase_request.submitted:{purchase_request_id}`; the event payload stores only integer IDs and the original submission timestamp.
* A separate `ops:outbox:publish` worker claims a bounded batch using `SELECT ... FOR UPDATE`, leases messages for 90 seconds and releases DB locks **before** touching Redis.
* Enqueue success marks the row `dispatched`. A crash between queue acceptance and the DB ACK produces possible duplicate enqueue; **delivery is at least once, never exactly once**.
* The existing `RecordPurchaseRequestSubmitted` consumer is idempotent using the DB's unique `activity_logs.idempotency_key` constraint. Do not use a check-then-insert for deduplication.
* Transport failures use exponential backoff with a bounded 12 attempts; terminal failures are visible and require operator review via `ops:outbox:retry {id}`. Retry does not bypass idempotence.
* Use an interface-based allow-listed transport. Never unserialize executable job names or dispatch arbitrary classes from DB payloads.

## Consequences / boundaries

* Outbox publication is not instantaneous. The optional Docker `outbox` service must be enabled **after migrations**. Without a running publisher, pending messages accumulate but business submission still succeeds.
* A `dispatched` row confirms acceptance by the queue transport, **not** successful processing by a worker; monitor failed jobs separately.
* The lease protects claims but cannot eliminate duplicates after worker pause/crash; consumers must be idempotent.
* An operator must monitor age/count of pending and terminal failed events. DB is the durable source of truth, Redis is the transport.
* `ops:outbox:prune` provides an explicit dry-run-first retention tool for old `dispatched` rows only; no automatic deletion of undelivered records.
* Applying outbox to new domains requires a per-event idempotent consumer and explicit design review; this phase deliberately does not rewrite unrelated workflows.

## Alternatives considered

1. **Keep `afterCommit` dispatch:** simpler but cannot recover the commit-to-queue failure window.
2. **Write to Redis in the DB transaction:** still lacks atomicity across two independent systems and holds DB locks during network I/O.
3. **Write the business audit record directly in the same DB transaction:** preferable if the only requirement is a DB audit. This outbox retains the existing asynchronous boundary and supplies a durable event for later consumers; it must not be generalized without an actual downstream need.
4. **Distributed transaction / two-phase commit:** disproportionate complexity and operational constraints for this system.

## Verification

`OutboxReliabilityTest` covers rollback, delayed enqueue, transport failure/retry, expired lease, terminal handling and duplicate consumer calls. `MySqlOutboxConcurrencyTest` uses independent MySQL processes to test simultaneous claims. The tests provide narrow evidence only, not production delivery guarantees under all infrastructure failures.
