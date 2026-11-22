# v21: Observability + Security Hardening (local-first)

**Scope:** extends existing Laravel 12 code, does not replace procurement orchestrators, migrations, React demo, API contracts or .env files. This is a reproducible **local/staging** operations demonstration, **not** evidence that a production backend is deployed, audited or secure.

## 1. Clone and prepare the checked-in project (Windows / PowerShell)

v21 is already committed in this repository; **no overlay ZIP is needed**. For a new checkout:

```powershell
git clone https://github.com/Will-ammari/procurepilot-ai.git
cd procurepilot-ai
composer install
# Only on a fresh checkout with no .env file:
if (-not (Test-Path .env)) { Copy-Item .env.docker.example .env }
```

Before launching Docker, edit `.env` locally: replace the example MySQL and Grafana passwords with unique development secrets. If this is a **fresh, never-used environment** and `APP_KEY` is empty, run `php artisan key:generate` **once**. Do **not** overwrite an existing `.env`, rotate an established `APP_KEY`, or assume changing MySQL environment variables updates a previously initialized database account. Preserve existing Docker volumes and data. The `.env` file must remain excluded from Git.

Windows Docker Desktop must have drive access, and the bind-mounted project needs Composer dependencies (`vendor/`).

## 2. Configure local secrets (NOT in Git)

Edit your existing project `.env`, NOT `.env.example` or `.env.docker.example`. Ensure the existing Laravel `DB_PASSWORD` matches the MySQL user password in Docker and is unique. Set a new local Grafana password:

```dotenv
OPS_METRICS_ENABLED=true
OPS_EXPORT_WINDOW_MINUTES=5
OPS_EXPORT_INTERVAL_SECONDS=15
GRAFANA_ADMIN_USER=admin
GRAFANA_ADMIN_PASSWORD=choose-your-own-long-random-password
MYSQL_ROOT_PASSWORD=choose-another-long-random-password
```

**For a new Docker database only:** `.env.docker.example` documents the required DB/Redis/queue settings. Generate an `APP_KEY` once for the new environment, before any encrypted data; never replace an existing key or rotate existing database credentials without a coordinated migration. Compose falls back to historical `secret` / `root` passwords when variables are missing to preserve the existing **local-only** developer workflow; these fallbacks are **not production credentials**. Changing environment variables alone does **not** rotate users/passwords stored in an already-initialized MySQL volume.


## 3. Local start, verification and monitoring access

Start on a **development** database, not a production or customer database:

```powershell
composer lint
composer analyse
php artisan test
php artisan test --filter=OperationalExportAndSecurityTest

docker compose --profile ops --profile monitoring config --quiet
docker compose --profile ops --profile monitoring up -d --build
# Existing schema only: non-destructive migrations; seed ONLY on an intentionally disposable demo DB.
docker compose exec -T app php artisan migrate
# If an existing app container was recreated, recreate Nginx too if upstream routing returns 502.
docker compose --profile ops --profile monitoring ps
docker compose --profile monitoring logs --tail=60 metrics-exporter
# Single-shot textfile export check:
docker compose --profile monitoring exec -T metrics-exporter php artisan ops:metrics:export --output=/metrics/procurepilot.prom --minutes=5
# The protected endpoint should return JSON 401 without a token.
curl.exe -i http://127.0.0.1:8000/api/v1/purchase-requests
# Public API should NOT expose the metrics endpoint.
curl.exe -i http://127.0.0.1:8000/internal/metrics
# Host and container application configuration preflight:
docker compose exec -T app php artisan ops:security:audit
```

The expected protected-API response is **401** with a JSON `unauthenticated` error, request ID and **no** stack trace. `internal/metrics` is not a public API route (expected 404). `APP_DEBUG=false` and a valid stable `APP_KEY` are required in the Docker runtime. `https_app_url` may remain `REVIEW` on local HTTP; production exposure would require real HTTPS/proxy and secret-management controls.

Open **http://127.0.0.1:3000** and sign in with the local Grafana credentials. The provisioned dashboard appears under **Dashboards > ProcurePilot**. The metrics HTTP endpoint and Prometheus do not publish host ports; Grafana binds to `127.0.0.1:3000`. All other published development ports in the reviewed Compose configuration are also loopback-only. Never expose the local stack through a public tunnel or reverse proxy without an independent security review.

For Prometheus Explore queries use `up{job="procurepilot"}` and `procurepilot_http_requests_window{status_class="4xx"}`. The exporter snapshot uses Redis-backed rolling windows; these series are **gauges, not lifetime Prometheus counters**. Do **not** use `rate()` on `procurepilot_*_window`. `CACHE_STORE=array` makes per-process HTTP counters invisible to an exporter in a separate container. A stale `.prom` file is diagnosed by `procurepilot_export_timestamp_seconds`; snapshots must not silently present failed dependencies as zero.

## 4. Metrics, signal boundaries and costs

- `procurepilot_http_requests_window{status_class="all|4xx|5xx"}` tracks request completions in the last N minutes; no path, user or tenant labels.
- `procurepilot_events_window{event="..."}` tracks AI success/failure and outbox delivery/retry/failure in the same window.
- `procurepilot_duration_window_bucket{operation="http|ai",le="..."}` is a **fixed bucket cumulative window gauge**, not a Prometheus native Histogram. Do not use histogram_quantile against these without understanding the semantics and sampling.
- `procurepilot_outbox_messages{status="pending|processing|failed"}` and `procurepilot_oldest_undelivered_age_seconds` read actual outbox state. Dispatched history is excluded from frequent scans; a rolling window dispatch signal is available under events.
- `procurepilot_queue_depth` and `procurepilot_failed_jobs` use **-1 for unavailable**, never zero if backend is unreachable.
- `procurepilot_export_timestamp_seconds` detects stalled exporter, even if last .prom file remains on disk.

At 15-second export intervals the exporter performs a bounded number of cache reads, two outbox queries, optional queue lookups and a `failed_jobs` count. This is a local diagnostic design, not a documented p99/SLO claim. On large tables, measure query plans and indexing. The current metrics store retains minute buckets for two hours; the default dashboard shows one hour, Prometheus keeps seven days locally.

`X-Request-Id` and W3C `traceparent` already propagate through Laravel and the optional FastAPI boundary; correlation **is not full distributed tracing**. Structured JSON logs are opt-in via `LOG_CHANNEL=ops_json` (and `LOG_LEVEL=info`), emitted to stderr for `docker compose logs`. Avoid logging request bodies, authentication secrets and quote payloads. Review logs for sensitive data before sharing them.

## 5. Repeatable failure detection and recovery (local only)

**Verified on November 22, 2026:** a controlled `metrics-http` stop caused Prometheus `up{job="procurepilot"}` to change **1 -> 0**. The `ProcurePilotMetricsUnavailable` rule reached **Pending** and **Firing**, and `up` recovered to **1** after restarting the metrics endpoint. These observations came from local Docker/Prometheus/Grafana; they are not a production incident, a notification-delivery test or a measured detection-time SLO.

The exact rule is `absent(procurepilot_export_timestamp_seconds) or (time() - procurepilot_export_timestamp_seconds > 120)`, with `for: 2m`. It is **not** equivalent to an alert with `up == 0`; the target can be down before the rule has stayed true long enough to fire. Evaluation interval and scrape interval are 15 seconds by default.

```powershell
# Inspect baseline in Grafana Explore: up{job="procurepilot"} == 1
# Local fault injection: this interrupts ONLY the metrics HTTP serving endpoint.
docker compose --profile monitoring stop metrics-http
# Inspect in Grafana Explore: up{job="procurepilot"}
# Inspect alert evaluation: ALERTS{alertname="ProcurePilotMetricsUnavailable"}
# Leave the outage long enough for the alert expression + for: 2m.
# Restore, even if the alert never fires:
docker compose --profile monitoring start metrics-http
# Re-run up{job="procurepilot"}; expect 1 after the next successful scrape.
```

For a **separate synthetic-data-only outbox exercise**, the [outbox operations runbook](PHASE3_RUNBOOK.md) explains publisher pauses, backlog, retries and idempotent audit verification. Do not stop databases, queue workers or mutate real business records merely to demonstrate alerting. Capture the injected fault, expected vs observed signal, alert lifecycle, recovery action and evidence without inventing customer impact.

Five version-controlled Prometheus rules are in [`docker/monitoring/alerts.yml`](../docker/monitoring/alerts.yml): exporter unavailable/stale, outbox backlog, oldest undelivered event, terminal failures and API 5xx. They are **Prometheus-managed**, not Grafana-managed. **No Alertmanager target or external notification receiver is configured** in [`prometheus.yml`](../docker/monitoring/prometheus.yml). Seeing Pending/Firing in Grafana does **not** prove an email, Slack message or pager notification was delivered.

## 6. Security guardrails

- Laravel API final responses (including exception responses) use `Cache-Control: no-store`, no sniffing, deny framing, no referrer, restricted permissions/CSP.
- Login rate-limit identities are hashed before creating cache keys; existing per-IP limits stay in place. Cross-origin requests remain restricted by the existing `FRONTEND_ALLOWED_ORIGINS` allowlist.
- Docker-published development ports bind to **127.0.0.1**, including MySQL, Redis, SMTP, API and FastAPI; no public metrics endpoint was added.
- PHP version disclosure is disabled in the container's php.ini and Nginx `server_tokens` is disabled. Grafana requires configured credentials.
- Internal services are *not* a substitute for perimeter TLS, firewall/IAM, secret rotation, backups or external penetration testing.
- New deployment preflight: `php artisan ops:security:audit --strict` checks production-oriented application settings **without displaying secret values**. It will fail a normal debug-enabled HTTP local configuration by design; run it in a prepared staging/production-like environment rather than disabling tests to force green.

See [V21 security threat model](V21_SECURITY_THREAT_MODEL.md) for threats, residual risks and a deployment checklist.

## 7. Rollback

Check changes with `git status --short`. If you have local uncommitted documentation or configuration changes, review `git diff` and revert only the intended paths, preserving unrelated changes; do not delete databases, migrate:fresh or `docker compose down -v`. To stop new monitoring without touching the app or its data:

```powershell
docker compose --profile monitoring stop metrics-exporter metrics-http prometheus grafana
```

If necessary, revert only the specific v21 code/config changes through version control after reviewing their impact. Existing Outbox data must remain intact. The v21 observability/security change did not add DB migrations. Keep the old `ops` outbox publisher running when needed for the existing async audit workflow. For rollback from a deployed service, restore versioned config with a planned rollback, test API and job delivery before declaring completion.

## 8. Full verification & limitations

```powershell
composer lint
composer analyse
php artisan test
php artisan ops:security:audit
php artisan route:list
# Disposable database only; see docs/PHASE3_RUNBOOK.md for the MySQL race test invocation.
cd frontend
npm ci
npm run typecheck
npm run lint
npm run format:check
npm run test
npm run test:tooling
npm run build
npm run test:e2e
cd ..
git diff --check
git status --short
```

Re-run relevant checks after a checkout, configuration change or dependency update; source-lint alone does not prove production readiness. v21 does **not** change public Vercel frontend behavior. Adding a monitoring UI to React would be an unnecessary new trust boundary.
