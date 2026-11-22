# v21: Observability + Security Hardening (local-first)

**Scope:** extends existing Laravel 12 code, does not replace procurement orchestrators, migrations, React demo, API contracts or .env files. This is a reproducible **local/staging** operations demonstration, **not** evidence that a production backend is deployed, audited or secure.

## 1. Apply the rootless ZIP on Windows

The package has paths such as `app/`, `docker/`, `.github/`, `tests/`, `docs/` **at ZIP root**. It contains only changed/new project files, NOT an additional project folder, and has no `.env`, `.git`, vendor, node_modules, database or customer data. Back up the repository first:

```powershell
cd D:\procurepilot-ai
git status --short
git switch -c feature/v21-observability-security
# Back up tracked changes / save DB and attachments before any real deployment.
Expand-Archive -Path "$env:USERPROFILE\Downloads\procurepilot-v21-observability-security-overlay.zip" -DestinationPath . -Force
```

**Do not delete the project folder or replace your `.env`.** Inspect changed files via `git diff --stat` and `git diff --check`. This archive is based on the uploaded code snapshot from 22 Nov 2026; if your checkout differs, review diffs before overwriting files.

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

**For a new Docker database only:** `.env.docker.example` documents the required DB/Redis/queue settings. Copying it into a *new* `.env` requires `php artisan key:generate`; never overwrite a current `.env` or rotate existing database credentials without a coordinated migration. Compose falls back to historical `secret` / `root` passwords when variables are missing to preserve the existing **local-only** developer workflow; these fallbacks are **not production credentials**. Changing environment variables alone does **not** rotate users/passwords stored in an already-initialized MySQL volume.

**Windows Docker Desktop note:** mounts must have access to your working drive. PHP container must have `vendor/` installed. For a new checkout, install deps using the existing runbook; compose builds image but does not run `composer install` automatically.

## 3. Local start + safety gates

```powershell
# Existing tests first
composer install
composer lint
composer analyse
php artisan test
php artisan test --filter=OperationalExportAndSecurityTest

docker compose config --quiet
docker compose up -d --build app nginx db redis queue
docker compose exec app php artisan config:clear
# If outbox publisher has previously been enabled, keep it running:
docker compose --profile ops up -d outbox
# First run will require GRAFANA_ADMIN_PASSWORD in your local .env
docker compose --profile monitoring up -d --build metrics-exporter metrics-http prometheus grafana

docker compose --profile monitoring ps
docker compose --profile monitoring logs --tail=60 metrics-exporter
# Manual single-shot exporter verification
docker compose --profile monitoring exec metrics-exporter php artisan ops:metrics:export --output=/metrics/procurepilot.prom --minutes=5
# Public API should not expose metrics
curl.exe -i http://localhost:8000/api/v1/health
curl.exe -i http://localhost:8000/internal/metrics  # expected 404
```

Open **http://localhost:3000** and sign in with your configured Grafana username/password. Open **Dashboards > ProcurePilot > ProcurePilot | Local Operations**. Dashboard is provisioned from versioned JSON. Prometheus and metrics HTTP endpoints are only on an **internal Docker network** and have no published ports; Grafana is bound to `127.0.0.1:3000`. Do not expose it through public reverse proxies or tunnels. If a password is missing, the Grafana container intentionally refuses to start.

If the exporter fails, the previous `.prom` textfile becomes **stale**. The `ProcurePilotMetricsUnavailable` rule alerts after age threshold; snapshot failures never masquerade as zero. Counts represent **rolling-window gauges**, NOT monotonic Prometheus counters, so **do not use `rate()`** on `procurepilot_*_window` series. Metrics are best-effort, cache-backed and require **shared Redis cache across API + exporter** in Docker. With `CACHE_STORE=array`, process-local counters will not aggregate; in that case only outbox/queue DB metrics remain meaningful.

## 4. Metrics, signal boundaries and costs

- `procurepilot_http_requests_window{status_class="all|4xx|5xx"}` tracks request completions in the last N minutes; no path, user or tenant labels.
- `procurepilot_events_window{event="..."}` tracks AI success/failure and outbox delivery/retry/failure in the same window.
- `procurepilot_duration_window_bucket{operation="http|ai",le="..."}` is a **fixed bucket cumulative window gauge**, not a Prometheus native Histogram. Do not use histogram_quantile against these without understanding the semantics and sampling.
- `procurepilot_outbox_messages{status="pending|processing|failed"}` and `procurepilot_oldest_undelivered_age_seconds` read actual outbox state. Dispatched history is excluded from frequent scans; a rolling window dispatch signal is available under events.
- `procurepilot_queue_depth` and `procurepilot_failed_jobs` use **-1 for unavailable**, never zero if backend is unreachable.
- `procurepilot_export_timestamp_seconds` detects stalled exporter, even if last .prom file remains on disk.

At 15-second export intervals the exporter performs a bounded number of cache reads, two outbox queries, optional queue lookups and a `failed_jobs` count. This is a local diagnostic design, not a documented p99/SLO claim. On large tables, measure query plans and indexing. The current metrics store retains minute buckets for two hours; the default dashboard shows one hour, Prometheus keeps seven days locally.

`X-Request-Id` and W3C `traceparent` already propagate through Laravel and the optional FastAPI boundary; correlation **is not full distributed tracing**. Structured JSON logs are opt-in via `LOG_CHANNEL=ops_json` (and `LOG_LEVEL=info`), emitted to stderr for `docker compose logs`. Avoid logging request bodies, authentication secrets and quote payloads. Review logs for sensitive data before sharing them.

## 5. Reproducible failure drill (synthetic development data only)

1. Create a **synthetic** tenant/request and submit once with the API in local Docker; record the returned Request ID. Confirm outbox/queue consumers run (`docker compose --profile ops ps`).
2. Stop **only** the `outbox` publisher (`docker compose --profile ops stop outbox`), submit one additional synthetic request, wait over 5 minutes (or use a shorter **temporary local-only** alert threshold), and inspect `procurepilot_oldest_undelivered_age_seconds` in Grafana. The OutboxOldestEvent alert should become pending/firing based on `for:` time.
3. Restart publisher `docker compose --profile ops start outbox`; verify pending count/age decline, verify only one matching idempotent activity log exists, and inspect `queue` and `outbox` logs for failures.
4. For database outage in a disposable environment: stop db, confirm metrics snapshot timestamp becomes stale, then start db, export again, and verify recovery. **Never stop a production DB just to demonstrate monitoring.**
5. Write down: fault, time detected, customer-visible symptoms (if any), recovery action, verification evidence, and the metric/alert screenshot. No fabricated incident history.

Alerts reside in Prometheus rules. **There is no external notification receiver or on-call integration** in this overlay; Grafana panels and Prometheus rules are local only. Wire a protected Alertmanager/email/chat receiver only after an organization-approved incident process and secret storage exist.

## 6. Security guardrails

- Laravel API final responses (including exception responses) use `Cache-Control: no-store`, no sniffing, deny framing, no referrer, restricted permissions/CSP.
- Login rate-limit identities are hashed before creating cache keys; existing per-IP limits stay in place. Cross-origin requests remain restricted by the existing `FRONTEND_ALLOWED_ORIGINS` allowlist.
- Docker-published development ports bind to **127.0.0.1**, including MySQL, Redis, SMTP, API and FastAPI; no public metrics endpoint was added.
- PHP version disclosure is disabled in the container's php.ini and Nginx `server_tokens` is disabled. Grafana requires configured credentials.
- Internal services are *not* a substitute for perimeter TLS, firewall/IAM, secret rotation, backups or external penetration testing.
- New deployment preflight: `php artisan ops:security:audit --strict` checks production-oriented application settings **without displaying secret values**. It will fail a normal debug-enabled HTTP local configuration by design; run it in a prepared staging/production-like environment rather than disabling tests to force green.

See [V21 security threat model](V21_SECURITY_THREAT_MODEL.md) for threats, residual risks and a deployment checklist.

## 7. Rollback

Check changes with `git status --short`. If you have **not committed** the patch, use version control to restore only **files from this overlay**, preserving any unrelated changes; do not delete databases, migrate:fresh or `docker compose down -v`. To stop new monitoring without touching the app or its data:

```powershell
docker compose --profile monitoring stop metrics-exporter metrics-http prometheus grafana
```

If necessary, stop or revert only code/config introduced by this overlay. Existing Outbox data must remain intact. There are **no new DB migrations** in this patch. Keep the old `ops` outbox publisher running when needed for the existing async audit workflow. For rollback from a deployed service, restore versioned config with a planned rollback, test API and job delivery before declaring completion.

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

Container dependencies and the original test suite must be validated locally/CI after overlay; source-lint alone does not prove production readiness. This patch does **not** change public Vercel frontend behavior. Adding a monitoring UI to React would be an unnecessary new trust boundary.
