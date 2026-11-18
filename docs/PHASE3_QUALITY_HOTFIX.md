# ProcurePilot Phase 3 — Quality hotfix (release candidate)

## Scope

This overlay contains **only changed files** relative to the Phase 3 overlay, plus this note. It does **not** include `.env`, `.git`, database data or vendor files. Do not apply it to Phase 1 without applying Phase 3 first.

## Fixes

- Corrects the declared histogram-key type in `OperationalMetrics` (numeric-looking bucket keys are converted to integer keys by PHP).
- Reads the Outbox payload through Eloquent's casted attribute API while retaining strict runtime payload validation.
- Revises a fault-injection test to assert the attempted failing cache write, and narrows the histogram type for PHPStan.
- Makes the rollback test assert the captured exception without an unreachable statement; also formats the anonymous transport classes and sorts imports.
- Updates the legacy API activity-log test for the **asynchronous outbox contract**: first check the pending outbox message, then explicitly publish, then verify the committed audit log.
- Adds a malformed-event-payload regression test.

## How to apply

1. Back up your current work and overlay the ZIP at the project root.
2. Run these in PowerShell from the directory containing `docker-compose.yml`:

```powershell
docker compose exec app composer lint
docker compose exec app composer analyse
docker compose exec app php artisan test --filter=ActivityLogApiTest
docker compose exec app php artisan test --filter=OutboxReliabilityTest
docker compose exec app composer test
```

There are **no new migrations** and no need to rebuild images if source files are bind-mounted. If they are not, rebuild `app`, `queue` and `outbox`.

Before shipping, run **both** opt-in MySQL concurrency tests on a dedicated disposable database and verify GitHub Actions. Never target the primary `procurepilot` database with a test that calls `migrate:fresh`.

## Verification limits

The overlay has been syntax-checked and checked for unexpected files. PHPStan, Pint and the Laravel test suite must be re-run in the user's Docker environment. Passing syntax does **not** prove these pass. The application's live Outbox worker may produce no logs when idle; zero counters are not by themselves evidence of failure.
