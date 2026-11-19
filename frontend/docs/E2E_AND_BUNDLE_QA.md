# ProcurePilot frontend v18 — browser QA and bundle evidence

## Scope and trust boundaries

The Playwright suite runs with `VITE_DATA_MODE=demo` on a dedicated Vite port (4173), and uses **synthetic, isolated in-browser data**. Its passing results do **not** demonstrate Laravel API authorization, MySQL, Redis, real invoices, or deployed infrastructure. Test those separately with your Laravel Feature/Integration/Concurrency suites.

The suite includes:

- Request creation and submission by a requester.
- Quotes from two distinct suppliers; a generated comparison; sequential approval by manager, finance and admin (no requester self-approval).
- Creating and settling an invoice as finance.
- Viewer read-only affordances and attempted access to the request creation screen.
- Empty search, invalid form and missing record/error display.
- Responsive navigation and form layout at 375, 768 and 1440 CSS px, in Chromium desktop and emulated mobile profiles.

`test:e2e` runs all tests in both Chromium projects. A Playwright browser engine installation is required once per machine. Tests save a trace/screenshot/video on failure only. Browser contexts isolate demo localStorage between tests.

## Install and execute on Windows PowerShell

Run from `D:\procurepilot-ai\frontend`:

```powershell
npm install
npx playwright install chromium
npm run typecheck
npm run test
npm run build
npm run test:e2e
npm audit
npm audit --omit=dev
```

If Chromium setup asks for system libraries on Linux or in CI, run `npx playwright install --with-deps chromium`. The CI workflow does this automatically.

Playwright's local web server is configured on `http://127.0.0.1:4173`, so it does not interfere with the normal Vite dev server on 5173. Stop an unrelated process on port 4173 before running tests.

## Bundle size: reproducible comparison

Previously observed **single JS bundle** (user's v17 build, Vite output):

- raw `583.09 kB`; gzip `181.43 kB`.
- This is a **build artifact size**, not page load time.

v18 enables Vite's build manifest. `npm run build` automatically invokes `scripts/report-bundle.mjs`, which:

1. Reads `dist/.vite/manifest.json`.
2. Sums the entry chunk **and recursively referenced static JS imports** for initial JS byte size (each file once).
3. Reports all JS byte sizes and the per-route lazy entry assets.
4. Fails if the manifest lacks dynamic entry chunks (regression protection).

It does not claim route-level real-user LCP, page speed, cache performance, network transfer compression, or Lighthouse scores. Those require real browser/network measurement. CSS and HTML are excluded from the numbers.

## Quality gates before a public demo

- `npm run typecheck`, `npm run test`, `npm run build`, `npm run test:e2e` all pass.
- `npm audit` reviewed; investigate alerts instead of running `npm audit fix --force` blindly.
- Confirm new `frontend/package-lock.json` created by `npm install` is committed with package.json; CI uses `npm ci` for reproducibility.
- Validate no private `.env`, tokens or test artifacts are committed.
- Use `VITE_DATA_MODE=demo` on Vercel; label the page as a **simulated portfolio demo**, not a live API.
- Re-run Laravel tests independently for production/API permissions.

## Architectural changes

Route components use `React.lazy` + `Suspense` at the router boundary. The repository/API contract, forms, domain policies and UI code remain separate; feature files are not duplicated. Dynamic imports are defined **outside** React component render functions to keep component identity stable.

This update is *not* a rewrite into server-side MVC. In this client, route modules orchestrate UI and data access through the repository boundary; Laravel controllers remain the backend HTTP orchestration layer.

## Known remaining work

- User must run and verify the complete Playwright browser suite on an installed system (and in GitHub Actions). It could not be executed in the constrained ZIP-authoring environment because npm packages and Playwright were not installed there.
- Performance budget and accessibility automation (e.g. axe) can be added once real metrics are observed.
- Review UI forms for validation error semantics against **real** Laravel API contracts as a separate integration exercise.
