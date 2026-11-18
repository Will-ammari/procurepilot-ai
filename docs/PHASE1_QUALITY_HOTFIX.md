# Phase 1 quality hotfix (18 November 2026)

This patch overlays the previous Phase 1 Hardening archive. Extract **into the Laravel repository root**, not into an extra nested directory. It does not include .git, .env, vendor, Docker volumes or any database files. No commits or pushes were performed.

## Changes

- Fix PHPStan's stream type check by accepting **only** actual resources when copying legacy attachments.
- Keep the quote-analysis status fallback while removing a redundant nullsafe operator flagged by PHPStan.
- Replace a Mockery fluent expectation in `HealthCheckTest` with a PHPUnit mock that explicitly verifies `run()` is invoked exactly once.
- Normalize imports and references in six Pint-flagged files; clarify negated `instanceof` checks.
- Add `pdo_sqlite` to the application Docker image, because `phpunit.xml` selects the SQLite in-memory driver for the main test suite.
- Explicitly enable `pdo_sqlite` in the CI PHP environment.

## Apply locally (PowerShell)

1. Back up any local changes to the files listed above, then extract this ZIP in `D:\procurepilot-ai`, keeping the file paths.
2. From that directory, run:

```
docker compose config --quiet
docker compose up -d --build app queue
docker compose exec app php -m | findstr /i "pdo_sqlite"
docker compose exec app composer lint
docker compose exec app composer analyse
docker compose exec app composer test
docker compose ps
```

If the site is actively being used, plan for the brief app/worker container restart first. **Do not** run `migrate:fresh` against the development or production database.

## Verification scope

The source files in this patch were checked with `php -l`. Docker build, Pint, PHPStan and PHPUnit have **not** been re-run inside the author's environment; validate them with the commands above and inspect the results before releasing. An old or cached Docker image may not reflect Dockerfile changes until it is rebuilt.
