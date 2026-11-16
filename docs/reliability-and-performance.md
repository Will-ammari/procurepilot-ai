# ProcurePilot AI: Reliability & Performance Notes

## What changed

1. **Approval state:** Purchase request row is locked first, followed by its approval step. Status, previous-step ordering and authorization are verified after locking. Sending for approval, quote comparisons and quote edits use the same parent lock. Existing routes, resources and policy rules remain intact.
2. **Request submission:** the draft transition and its queued event are serialized on the purchase-request row. Normal dispatch still uses Laravel `afterCommit()`.
3. **Idempotent submitted audit:** a new nullable `activity_logs.idempotency_key` has a UNIQUE index. Each submitted-request job uses `purchase_request.submitted:{id}`. Replayed or overlapping jobs resolve to the existing record. Historical logs remain untouched; the migration does not retroactively deduplicate them. **Not an outbox:** a crash between transaction commit and queue publish still requires operational reconciliation.
4. **Invoice integrity:** creation locks the purchase request; update and mark-paid lock the request before the invoice, then revalidate status. Invoice listing caps `per_page` at 100.
5. **Quote analysis:** the FastAPI call is made outside a database transaction. The service then locks the parent request and quote, reloads vendor and items, compares a snapshot fingerprint, and refuses to save stale results. The `AI_SERVICE_ENABLED` environment flag is now correctly read via `config/services.php` (disabled by default). The provider response must have a matching quote ID, usable summary, bounded confidence score and correct types. Fallback on network, HTTP and invalid-response failures is deterministic. Unexpected internal bugs are not silently swallowed.
6. **Quote editing/comparison:** quote writes and comparison generation lock the request first, so they cannot race into an already-started approval workflow.

## Required rollout order

1. **Back up** code and database; deploy to staging first. Verify the patch matches the provided source revision.
2. Overlay the supplied files in the project root (no `procurepilot-ai/` wrapper). The archive includes a new migration and a dedicated MySQL test config.
3. Run `php artisan migrate --force` before starting queue workers with the new code. Restart queue workers after deployment using `php artisan queue:restart`. If using Redis and Laravel workers, ensure the new migration is live before any queued job runs.
4. Run `php artisan test`, `php vendor/bin/pint --test`, and `php vendor/bin/phpstan analyse --memory-limit=512M` (or `composer quality`). Use a real MySQL staging environment for lock tests.
5. Only deploy to production after the application and queue tests, approval and invoice smoke tests, and GitHub Actions workflow all pass.

**Rollback:** Back up the affected files. The supplied source rollback restores existing files and removes new ones but does **not** automatically revert the database migration. The additive nullable column is backward-compatible with the previous application. If absolutely necessary, remove the `idempotency_key` unique index and column in a controlled maintenance window after a database backup; dropping the column loses event keys, but not audit events.

## Real MySQL concurrency (destructive only to dedicated test schema)

`phpunit.mysql.xml` runs **only** `MySqlApprovalConcurrencyTest`. It intentionally uses two independent PHP processes and separate MySQL connections. The test calls `migrate:fresh` and therefore **must only run on the isolated schema** `procurepilot_concurrency_test`. The test requires `PROCUREPILOT_CONCURRENCY_TEST=1` and rejects any other database name or `APP_ENV != testing` before touching schema.

Create an empty MySQL database named `procurepilot_concurrency_test` and grant a test-only account full privileges to that schema, **not** to production. On PowerShell:

```powershell
$env:APP_ENV = 'testing'
$env:PROCUREPILOT_CONCURRENCY_TEST = '1'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'                 # use your published local MySQL port
$env:DB_DATABASE = 'procurepilot_concurrency_test'
$env:DB_USERNAME = 'your_test_user'
$env:DB_PASSWORD = 'your_test_password'
$env:DB_URL = ''
php artisan config:clear
php vendor/bin/phpunit --configuration=phpunit.mysql.xml
```

If MySQL runs only inside Docker, run the test within the app container with `DB_HOST=mysql` and port `3306`, or use its published host port. The dedicated GitHub Actions workflow creates the isolated schema and executes the same test automatically. It expects one accepted decision, one denied decision, one final approval-step state, and exactly one decision audit event. This tests a specific contention scenario, not every possible interleaving.

## Query review (P2)

Existing migrations already contain indexes for `approval_steps(purchase_request_id,sequence)` (unique), `approval_steps(purchase_request_id,status)`, `invoices(organization_id,status)`, `invoices(purchase_request_id,status)`, and `activity_logs(organization_id,event,created_at)`. The patch adds only the uniquely needed `activity_logs(idempotency_key)` index. Do **not** add speculative indexes without workload evidence.

On a representative **staging** database, inspect the dominant queries with database slow query logging and `EXPLAIN ANALYZE` (MySQL 8+). Candidates:

```sql
EXPLAIN ANALYZE SELECT * FROM approval_steps WHERE purchase_request_id = 123 AND sequence < 3 AND status IN ('pending', 'rejected');
EXPLAIN ANALYZE SELECT * FROM invoices WHERE organization_id = 1 AND status = 'received' ORDER BY created_at DESC LIMIT 15;
EXPLAIN ANALYZE SELECT * FROM purchase_requests WHERE organization_id = 1 AND status = 'submitted' ORDER BY created_at DESC LIMIT 15;
SHOW INDEX FROM approval_steps;
SHOW INDEX FROM invoices;
SHOW INDEX FROM purchase_requests;
```

Measure P50/P95 API response latency, query count, row scans, lock wait time and index size before changing schema. The service layer already eager-loads key relations. For large datasets, review the offset-pagination cost and consider keyset pagination only as an explicit API-versioned change.

## AI disclosure and operational limits

`fastapi-service/app/providers/mock_provider.py` implements **deterministic heuristics**, not learned inference or model evaluation. The local fallback is also deterministic. Both return guidance for human procurement review; neither makes purchase approvals. The recorded `model_name` identifies the provider, and no guarantee is made that the confidence score is calibrated. Log fallback errors by exception type and quote ID, without logging sensitive quote content.

**Remaining work:** a transactional outbox for guaranteed queue delivery, dead-letter handling, operational tracing/metrics, load testing, and full threat modeling. The current tests do not establish a comprehensive security or production readiness certification.
