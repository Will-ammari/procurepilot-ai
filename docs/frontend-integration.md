# React frontend integration overlay (2026-11-19)

## Files touched

- `frontend/**` — new responsive React + TypeScript workspace, data/repository split, isolated demo adapter, docs and Vercel SPA config.
- `routes/api.php`, `app/Http/Controllers/Api/V1/ApprovalWorkflowController.php` — add request-scoped read of approval steps.
- `app/Http/Controllers/Api/V1/QuoteComparisonController.php` — permit reading a previously generated comparison if the caller may view the purchase request; creating a new comparison remains restricted by `compareQuotes`.
- `config/cors.php` — explicit local React dev origin allowlist; production origins must be explicitly configured.
- `docs/openapi.yaml` — document GET vs POST comparison correctly, list approvals read endpoint and correct approval response status.
- `tests/Feature/Api/V1/ApprovalStepsReadApiTest.php` — read ordering and cross-organization authorization coverage.
- `.github/workflows/frontend.yml` — independent frontend quality job.

## Merge guidance

This ZIP uses repository-root relative paths (`procurepilot-ai/…`). Extract over the same parent folder as the existing `procurepilot-ai/` directory, **not directly inside `frontend/`**. Back up your current working tree or apply to a new Git branch before extraction. Only new/modified files are present; there is no `vendor/`, `node_modules/`, `.env`, `.git`, original storage data, or copied log file.

## Verification

- TypeScript source syntax parsed and the standalone demo repository compiled and exercised with Node; the core lifecycle create → submit → two quotes → compare → manager/finance/admin sign-offs was checked (plus denial of unauthorized actions).
- PHP changed files pass `php -l`.
- **Not executed here:** complete Laravel PHPUnit suite and React npm dependencies/build, because this workspace lacks Composer/vendor installation and outbound npm registry access. Run `composer test`, `npm run typecheck`, `npm test`, and `npm run build` on your machine before merging/deploying.

## Contract / behaviour notes

- Existing Laravel JSON resources use `{data: ...}` and Laravel pagination metadata; the frontend repository adapter unwraps them.
- Existing API errors may use `{error:{message,code},errors:{...}}`; the adapter normalizes them to typed `AppError`.
- Real Sanctum bearer tokens are only held in JS memory in API mode. This is a deliberate lower-persistence choice; refresh requires reauthentication. A production SaaS with persistent sessions should use a deliberate same-site secure-cookie/BFF design rather than adding bearer storage to localStorage.
- Real quote comparisons require at least two received/analyzed offers and are snapshot checked. Outdated comparisons are not presented as a new generation.
- This frontend is deliberately **not** proof of a public production Laravel deployment. Its Vercel mode is transparent simulation.
