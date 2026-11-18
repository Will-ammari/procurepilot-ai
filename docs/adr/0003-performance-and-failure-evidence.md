# ADR-0003 — Repeatable baseline and fault testing before scaling

**Status:** Accepted as a measurement method, not as demonstrated performance results.

## Decision

* Provide two small k6 workloads: public health reads and authenticated read-only purchase-request listings. Both run locally/staging only, with limited virtual users and bounded duration.
* Do not run write-heavy tests against customer data. Use synthetic organizations and separate test credentials. Record host, CPU/RAM, PHP-FPM workers, DB size, Redis configuration, VUs, duration, error rate, p50/p95/p99 and throughput for every run.
* Fault injection at test level uses an `OutboxTransport` that fails deterministically, including retry exhaustion, expiration and idempotency. A separate opt-in MySQL process test exercises the real row-locking path. External FastAPI timeout tests exercise deterministic fallback.
* Compare with and without added metrics **on the same machine/environment**, using `OPS_METRICS_ENABLED` to isolate overhead. Include sampled exceptions and deployment-time incidents in later follow-ups.

## Acceptance / exclusions

* Suggested k6 thresholds are illustrative guardrails, not user-facing latency promises.
* A green synthetic test does not establish capacity limits, regional availability, queue throughput, durability under disk loss or security of a production deployment.
* Run real incident drills only in an isolated development/staging environment, not on active customer production infrastructure.
