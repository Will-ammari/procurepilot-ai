# ProcurePilot AI — Phase 1 hardening release

## Scope and architecture

This patch is an **overlay** for the existing Laravel 12 / FastAPI repository. It does not require a new project or a Git commit. Controllers remain thin HTTP adapters; validation remains in Form Requests; domain invariants, row locks and transactions live in application services; persistence lives in Eloquent models and migrations.

| Area | Previous risk | New invariant / behavior |
| --- | --- | --- |
| Docker | `queue` was nested under `app` and mis-indented | `queue` is a top-level service; config can be validated with `docker compose config --quiet` |
| Attachments | New documents were stored on a web-exposed public disk | All **new** uploads use the `local` private disk, with an organization-specific path; failed DB persistence attempts clean up the uploaded object |
| Existing attachments | Historical DB records may still reference web-public objects | `attachments:secure-public` copies verified files to private storage, updates the record and deletes the public original; dry run supported |
| Comparisons | GET created new persisted comparisons; edits could leave an old recommendation | POST generates; GET reads; comparison persists SHA-256 fingerprint of quotes, items, vendor and analysis data; approval rejects stale/legacy comparisons |
| AI data validity | Changing terms could leave an old analysis attached | Changing quote terms/items/status deletes outdated analysis and resets non-draft status to received |
| Currencies | Prices in different currencies could be scored as directly comparable | Comparison fails closed unless all eligible quotes use the request currency; invoice currency must match request currency |
| Hidden-cost scoring | Missing analysis looked like zero hidden costs | Unanalysed quotes receive no hidden-cost points and show `analysis_status` |
| Invoices | First paid invoice set the entire request to `paid` | Parent-row locked reconciliation: the request is paid only if at least one active invoice is paid and **no** active invoice remains unpaid; cancelled invoices are excluded |
| HTTP/API | No dedicated login rate limit; health route exposed environment and connection details | Named rate limiter for login; public health output shows only dependency status |
| CI | No FastAPI job or Compose schema validation in main CI | Adds Python test job and Compose check; existing Laravel style, static analysis, PHPUnit and MySQL concurrency workflows retained |

## Breaking / intentional API changes

`POST /api/v1/purchase-requests/{id}/comparison` **generates** a new comparison and returns `201`.

`GET /api/v1/purchase-requests/{id}/comparison` **reads** a previously generated, still-valid comparison. It returns `422` if missing or stale; it **never writes**.

Comparisons created before the new `snapshot_hash` migration have `NULL` fingerprints and **must be generated again** before approval. This fails closed rather than incorrectly certifying old comparison data.

`PATCH /api/v1/quotes/{id}` no longer accepts direct API assignment of `analysis_pending` or `analyzed`; those statuses belong to the analysis workflow. Updating analysis inputs removes stale analysis and may reset status to `received`.

Multi-currency purchasing is **not automatically converted**. Offers may be entered in other currencies, but comparisons and invoices deliberately reject unsafe FX assumptions. Use a verified conversion process before comparing.

## Safe installation on an existing working tree

**Backup your repository and data first.** The overlay ZIP contains relative paths such as `app/`, `routes/`, `database/`, and `.github/`. Extract it **inside the project root**, not into a second nested folder. It does not contain `.git`, `.env`, or a Git commit.

Recommended sequence for any environment with real users:

1. Save a backup of the database, `storage/app/public`, `storage/app/private` and the code. Schedule a maintenance window, and disable writes / queue workers during attachment migration.
2. Extract the overlay into the project root. Inspect the changes with `git diff` / `git status` before proceeding.
3. Run `composer install` (if needed), then `php artisan migrate --force` to create the nullable `quote_comparisons.snapshot_hash` column.
4. Run `php artisan attachments:secure-public --dry-run` and verify the number of exposed old files.
5. Run `php artisan attachments:secure-public`. **Do not proceed to public launch if it reports errors or leftover public DB records.** Check public storage for unreferenced leftovers or failed deletion reports. Rotate any external URL caches if these files were ever exposed.
6. Run quality checks on a development/staging environment, including `docker compose config --quiet`, `composer lint`, `composer analyse`, `composer test`, and the MySQL approval concurrency workflow. For FastAPI: `python -m pytest -q fastapi-service/tests`.
7. Regenerate existing comparisons before new approvals via the POST endpoint. Perform a smoke test: upload/download with tenant-scoped permissions, update a quote after comparison, try a mixed-currency comparison, and pay one of two active invoices.
8. Re-enable workers and user writes. Observe application logs for any migration, storage, throttling or request errors.

**Important:** The new schema migration does not move existing files. Historical attachment files remain at risk **until** `attachments:secure-public` completes and old public URLs / copies are checked. Do not simply delete your entire `public/storage` folder: unrelated assets may still depend on it.

## CI and verification status

The ZIP includes test source and GitHub Actions configuration, but CI success cannot be asserted without actual remote job logs. This environment had PHP CLI but **no Composer-managed vendor directory or PHPUnit/Pint/PHPStan binaries** and no Docker executable; those checks could not be run here. PHP syntax lint, YAML parsing and the available FastAPI tests were exercised. Reproducible full CI (including real MySQL race tests) remains a required acceptance gate before production.

## Operational notes

- The invoice status convention handles **whole invoices**; partial payments and credit notes need a separate ledger and reconciliation design, not a boolean `paid` flag.
- Changes to comparison inputs are revalidated at approval submission under the purchase request row lock. Keep that parent-first lock discipline on **every** path that can mutate quotes or analysis.
- Continue to audit third-party or direct database mutation paths; any code bypassing services and locks can bypass concurrency invariants.
- The public health endpoint is an availability probe. Detailed dependency error messages belong in access-controlled internal diagnostics; avoid logging secrets or sensitive customer payloads.
- Rollback of code alone may reintroduce the public-upload vulnerability; prefer forward fixes. Existing private files should not be moved back to public disk. Database backups should be used only within a planned recovery procedure.
