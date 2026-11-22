# v21 security threat model and production preflight

**Classification:** demonstration-oriented security hardening / defense in depth. Not a penetration test, compliance certification, SOC2/GDPR claim or production security signoff.

## Assets and boundaries

**High-value assets:** organization-scoped procurement data, supplier quote attachments, invoices, Sanctum bearer tokens, database credentials, async outbox event ledger, audit history, logs and metrics.

**Trust boundaries:** Internet/user -> API gateway (Nginx) -> Laravel auth/policies -> MySQL/Redis, Laravel -> optional FastAPI over private Docker service network, publisher -> queue -> idempotent consumer, internal exporter -> private metrics file -> Prometheus -> Grafana localhost. Public Vercel UI runs separate synthetic local demo; it is **not** connected to private Backend.

## Threat, mitigation, evidence and residual risk

| Threat | Implemented / existing control | Remaining actions before production |
| --- | --- | --- |
| Cross-tenant IDOR/BOLA | Existing Laravel Policies, organization-scoped queries, tenant feature tests | Re-test every new route, relation and queued job; external adversarial review |
| CSRF / token leakage / cached sensitive responses | Sanctum bearer API, explicit CORS allowlist, v21 `no-store` and denial of embedding | TLS, browser token lifecycle, client-side XSS review, rotate compromised tokens |
| Brute force / cache key pollution | Existing per-user/per-IP login throttles; v21 bounded hashed identity keys | Distributed abuse monitoring and real client IP handling behind trusted proxies |
| Secrets in logs and metrics | Existing sanitized application event logging; v21 fixed-dimension metrics, JSON opt-in channel | Secret scanning, log retention, scrubbing, access-control and incident policy |
| Public Redis/MySQL/ops service exposure | v21 local bind on 127.0.0.1; internal Docker network for Prometheus/export HTTP | Cloud firewalls, separate credentials, TLS/private VPC, no external reverse proxy to these services |
| Worker crash / lost async audit event | Existing outbox DB transaction, lease token, retry, idempotent consumer; v21 gauges and local alerts | Queued dead letters/runbook, real incident drills, durable receiver for alerts |
| Authentication / authorization regression | Existing FormRequests/policies and test suites, v21 security header regression test | DAST, dependency review, access-control matrix, cross-tenant fuzzing |
| Data corruption / operational loss | DB transactions and runbooks | Restore tests, encrypted backups, recovery time target, key rotation and backups offsite |
| Supply-chain compromise | Composer lock and Node lock, GitHub Actions CI | Audit with composer audit, npm audit, pinned Docker digests/scanners and dependency update procedure |
| Monitoring tampering | Exported metrics have fixed labels, private textfile and Grafana auth | Dedicated least-privilege telemetry identity, retention/access reviews, TLS and audited monitoring |

## Mandatory production checklist (not implemented by this local overlay)

1. Separate *production* infrastructure/identity from Docker dev. No default `secret`/`root` credentials, rotate all local demo passwords; use secret manager and non-root database users.
2. `APP_ENV=production`, `APP_DEBUG=false`, strong `APP_KEY`, `APP_URL=https://...`, HTTPS termination, trusted proxy configuration, HTTP->HTTPS redirects, HSTS at a real TLS edge, security updates and backups. Run `php artisan ops:security:audit --strict` in target environment.
3. Configure CORS origins explicitly; secure frontend token storage and login/session expiry. Verify 401, 403, 404 tenant behavior including event workers.
4. Separate firewall rules/allow-lists for MySQL, Redis, FastAPI, workers, Grafana/Prometheus. Do not publish an unprotected `/metrics` route.
5. Verify filesystem attachment privacy, virus/unsafe content handling if needed, upload size and MIME controls, object-level authorization and private storage ACLs.
6. Configure logging retention and redact request bodies, tokens, PII and procurement payloads. Do not ship `storage/logs`, `.env`, DB dumps, `node_modules` or `.git` in application handoff ZIP files.
7. Validate remote CI and concurrency suites on **disposable** MySQL databases. Do not use `migrate:fresh` on real data.
8. Perform baseline load and failure drills, reproducible rollbacks, disaster restore and external application security review before accepting live customers.

## Observability alert limitations

Prometheus rule files provide *local alert evaluation*, not paging. No promise is made about delivery, RPO/RTO, SLO, throughput or p99 because this patch has not measured a live production workload. `time() - procurepilot_export_timestamp_seconds` is the supported freshness diagnostic; an exporter error leaves the file stale rather than fabricating a healthy zero state.
