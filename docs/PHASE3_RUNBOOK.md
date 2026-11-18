# ProcurePilot AI — Phase 3 deployment and verification runbook

**Goal:** Durable `purchase_request.submitted` event publication, bounded operational metrics, correlated Laravel-to-FastAPI logs, reproducible fault and load checks. **Scope is deliberately narrow.** No AI model changes or new procurement features.

## 0. Preconditions (Windows PowerShell)

* Apply this overlay on top of the last Phase 1 hardening and hotfix ZIPs already validated on your machine.
* Back up the application database and take a recoverable snapshot of attached files; use a controlled deployment window.
* The overlay does not change `.env`, `.git`, Composer versions or API request/response contracts. It **does** change when submission audit jobs are enqueued: the outbox publisher must now run.
* Never run `migrate:fresh` against the real `procurepilot` database. MySQL concurrency tests explicitly require `procurepilot_concurrency_test`.

## 1. Deploy and migrate

```powershell
docker compose config --quiet
docker compose exec app php artisan migrate:status
docker compose exec app php artisan migrate
docker compose exec app php artisan config:clear
```

New migration: `2026_11_18_000002_create_outbox_messages_table.php` (adds only `outbox_messages`).

Start the new worker after confirming the migration ran:

```powershell
docker compose --profile ops up -d --build outbox
docker compose ps
docker compose logs --tail=50 outbox
```

Outbox is **opt-in** via Compose `ops` profile, so it is not started by a plain `docker compose up -d`. It must be running for asynchronous submission audit events to be published. Queue service must also be running to process them.

## 2. Gate checks before Git push or deployment

```powershell
docker compose exec app composer lint
docker compose exec app composer analyse
docker compose exec app composer test
docker compose exec app php artisan test --filter=OutboxReliabilityTest
docker compose exec app php artisan test --filter=ObservabilityTest
```

Run MySQL concurrency only against the **dedicated disposable** `procurepilot_concurrency_test` schema, using the exact opt-in invocation:

```powershell
docker compose exec -e APP_ENV=testing -e PROCUREPILOT_CONCURRENCY_TEST=1 -e DB_CONNECTION=mysql -e DB_DATABASE=procurepilot_concurrency_test -e DB_URL= app php vendor/bin/phpunit --configuration=phpunit.mysql.xml
```

This configuration now includes both the existing approval race test and a separate concurrent outbox publisher test. These use `migrate:fresh`: **never** point the command at production/staging DBs containing valuable data.

## 3. Observe (no public observability endpoint)

```powershell
docker compose exec app php artisan ops:metrics --minutes=5
docker compose exec app php artisan ops:outbox:publish --limit=50
docker compose logs --tail=100 outbox
docker compose logs --tail=100 queue
```

`ops:outbox:publish` is safe to repeat and explicitly bounded. Inspect `pending_or_processing`, `terminal_failures`, and `oldest_pending_created_at`; alert externally if counts/age grow. A delivered outbox event is only accepted by the queue: also monitor `failed_jobs` and actual consumer behavior.

Example trace check (PowerShell):

```powershell
curl.exe -i http://localhost:8000/api/v1/health
```

Check `traceparent` and `X-Request-Id`. Laravel application logs and FastAPI logs should carry the same trace ID when an analyzed quote uses FastAPI, but this is **log correlation**, not collector-backed OTel traces.

## 4. Reproducible load checks — staging or local ONLY

k6 is open-source; using Docker avoids installing a global CLI. The service must be running at `localhost:8000`. Docker Desktop exposes the host as `host.docker.internal` inside the k6 container.

```powershell
docker run --rm -v "${PWD}/tests/load/k6:/scripts:ro" -e BASE_URL=http://host.docker.internal:8000 grafana/k6 run /scripts/health.js
```

For authenticated **read-only** listing, create a token for a synthetic staging tenant, supply it via an environment variable (never add to Git), and run:

```powershell
docker run --rm -v "${PWD}/tests/load/k6:/scripts:ro" -e BASE_URL=http://host.docker.internal:8000 -e PROCUREPILOT_TEST_TOKEN=$env:PROCUREPILOT_TEST_TOKEN grafana/k6 run /scripts/authenticated_read.js
```

Record results instead of inventing performance claims: RPS, response-time p50/p95/p99, error-rate, DB query count and CPU/memory profiles. Repeat with `OPS_METRICS_ENABLED=false` to quantify instrumentation overhead. Restart PHP processes or clear config cache when changing `.env` values.

## 5. Fault injection

Automated tests cover failure after DB commit but before queue delivery, expiring leases, retry exhaustion, duplicate consumption and FastAPI connection failure. For an optional manual **local-only** Redis outage drill, submit a synthetic request **before** stopping Redis, then stop Redis, invoke a single `ops:outbox:publish` (expect backoff), restart Redis, wait for retry eligibility and re-invoke the publisher. Confirm exactly one audit entry for the submission; don't run on customer data. Ensure backups and stop any competing outbox daemon first for repeatability.

## 6. Retention without losing failed events

The outbox is a delivery ledger, not the permanent business audit. `activity_logs` is the business audit. Review retention with your organization's compliance policies. Only successfully queued rows are eligible for pruning; pending, processing and terminal-failed rows are never pruned by this command.

```powershell
docker compose exec app php artisan ops:outbox:prune --days=30
docker compose exec app php artisan ops:outbox:prune --days=30 --force
```

The first command is a dry-run. Do **not** prune before inspecting pending/failed events, and do not automatically schedule pruning until your recovery, compliance and audit policies are agreed.

## 7. Rollback / recovery

If publication causes problems, stop the outbox worker (`docker compose --profile ops stop outbox`). This **does not undo submitted requests**; pending events remain persisted. Fix the publisher, then restart to drain them. Do not drop `outbox_messages` on a rollback while it contains undelivered events. If reverting application code, plan a compatible event-drain/migration first.

For terminal failed events investigate the transport and logs, then:

```powershell
docker compose exec app php artisan ops:outbox:retry 123
```

Replace `123` with an actual failed outbox ID. Do not blindly retry unknown event types or expose this operation via an unauthenticated HTTP endpoint.

## Evidence checklist

- [ ] Migration ran on a backed-up database
- [ ] Normal PHPUnit, Pint, PHPStan green on the exact ZIP contents
- [ ] Both isolated MySQL concurrency tests green
- [ ] Outbox worker running and queue worker running
- [ ] New submission produces exactly one idempotent audit record
- [ ] Same trace ID seen in both Laravel and FastAPI logs for a synthetic analysis
- [ ] Recorded k6 baseline, environment, and comparison for metrics enabled/disabled
- [ ] GitHub Actions success on pushed commit (not evidenced by source YAML alone)
