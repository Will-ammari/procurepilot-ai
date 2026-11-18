# ADR-0002 — Lightweight bounded operational instrumentation

**Status:** Accepted for Phase 3 proof-of-concept; **not** a full tracing or monitoring deployment.

## Context

Phase 1 has `X-Request-Id`, health checks and audit trails, but no repeatable latency/error measurements between Laravel and FastAPI. Adding Grafana/Prometheus/OTel as a mandatory dependency to this portfolio build would increase operational surface without a workload baseline.

## Decision

* The API middleware creates trace context; a `RequestHandled` listener observes the final HTTP status (including rendered error responses) and emits safe structured completion logs with `trace_id`, `span_id`, `request_id`, route **name**, HTTP method, status and latency. No query strings, bodies, vendor names, credentials or document payloads.
* `traceparent` uses the W3C `00-<trace-id>-<span-id>-01` form, with a new span ID on each hop. Laravel forwards a validated `traceparent` and `X-Request-Id` to FastAPI. Invalid incoming IDs are replaced. FastAPI logs service hop timing using the same trace ID.
* Use bounded per-minute counters and fixed latency buckets in Laravel's cache (`OperationalMetrics`). Metrics are best-effort: failure must not block procurement requests. `ops:metrics --minutes=5` presents the counters **internally**, with outbox backlog and terminal failures from the DB. No public metrics route is introduced.
* Trace sampling (default 10%, all 5xx) controls normal API log volume; AI call completions are always logged without payloads. Metrics use fixed key dimensions, never user IDs, tenant IDs, paths with parameters or document names.

## Explicit limitations and tradeoffs

* Correlated logs plus `traceparent` are **not** OpenTelemetry spans, end-to-end distributed tracing, a durable time-series backend, or alerting. There is no Grafana/Tempo/Jaeger collector in this ZIP.
* Cache metrics are ephemeral and may be dropped on cache eviction, Redis outage or app restart; they are not accounting/billing data. The implementation adds cache writes per request. Measure overhead with k6 before expanding metrics.
* W3C trace context is only passed across the demonstrated Laravel -> FastAPI hop; it is not yet automatically propagated through outbox/queue workers.
* No SLO is claimed until representative deployments, workloads and monitoring windows are measured.

## Follow-up criteria

If real users require 24/7 diagnostics, move to OTel instrumentation + a controlled collector, a real metrics backend and alert routing; compare cost/latency before deployment. Use this implementation as a baseline rather than treating it as production observability certification.
