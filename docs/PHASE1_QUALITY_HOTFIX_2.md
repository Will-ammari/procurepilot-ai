# Phase 1 - Quality & Workflow Regression Hotfix 2

This is an overlay on top of **ProcurePilot_Phase1_Hardening_Updated_Overlay.zip** (or the original Phase 1 overlay plus the Quality Hotfix). Extract it into the Laravel project root, retaining directory paths.

## Changed files

- `app/Console/Commands/SecureLegacyAttachments.php`: close the already-validated stream directly in `finally` to satisfy PHPStan without disabling a rule.
- `app/Services/Procurement/ApprovalWorkflowService.php`: use `latest('id')` consistently when selecting the most recent comparison, including when two records share a `created_at` timestamp. This also fixes which quote becomes the approved quote.
- `tests/Feature/Api/V1/QuoteAnalysisApiTest.php`: force the deterministic local analyzer for local-analyzer feature tests, independently of `.env` and the external FastAPI availability. External integration remains exercised in `QuoteAnalysisReliabilityTest`.
- `tests/Feature/Procurement/PhaseOneHardeningTest.php`: cover regenerated comparison selection and the final approved quote, not only approval submission.

No migration, changes to `.env`, Git commit or Git push are necessary for this hotfix.

## Validation to run from Windows PowerShell

```powershell
docker compose exec app php artisan config:clear
docker compose exec app composer lint
docker compose exec app composer analyse
docker compose exec app php artisan test --filter=QuoteAnalysisApiTest
docker compose exec app php artisan test --filter=PhaseOneHardeningTest
docker compose exec app composer test
```

There is no need to rebuild Docker images for these PHP-only changes **if the application source is bind-mounted**. Otherwise run `docker compose up -d --build app queue` before the checks.

The opt-in MySQL concurrency test will remain skipped in the generic test run. Run its dedicated workflow separately when ready.

## Validation evidence

Local syntax validation is possible for the included PHP files. PHPStan, Pint and PHPUnit require the installed Composer vendor dependencies and project environment; they must be run on your Docker environment before the hotfix is declared fully verified.
