# ProcurePilot AI — React workspace

A responsive TypeScript interface for the existing Laravel procurement API. The frontend is a **separate application** (no changes to Laravel's Blade/Vite entrypoint). This is a portfolio artifact, not an independently certified production system.

## Modes

- **Demo (`VITE_DATA_MODE=demo`, default):** interactive fictional data and simulated workflow mutations stored locally in browser `localStorage`. Public Vercel deployment needs **no hosted backend**, API keys, paid hosting, or seeded real accounts. This mode never calls Laravel. Role switching demonstrates client-side visibility, **not server-enforced security**. Use **Reset demo** to restore fixtures.
- **Laravel (`VITE_DATA_MODE=api`):** authenticated REST calls to the existing `/api/v1` endpoints. The server remains authoritative for authorization, state transitions, consistency, and validation. Bearer tokens live **in memory only** and are intentionally lost on refresh; sign in again. Do not publish Laravel secrets in `VITE_` variables.

## Start locally

Requirements: Node 20.19+ or Node 22+, npm; internet access during first dependency install.

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

Open <http://127.0.0.1:5173>. Choose a persona and enter the demo. The frontend can run standalone without PHP, Docker, MySQL, or FastAPI.

### Connect the actual Laravel API

Start Laravel (and its required infrastructure) using the existing backend instructions, then edit `frontend/.env`:

```env
VITE_DATA_MODE=api
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

If Laravel runs in Docker at a different published port, use that accessible URL. Stop/restart `npm run dev` when modifying `.env`. Log in with your *local* seed credentials (`admin@procurepilot.test`, etc., default seed password `password` **only for local development**). Do not deploy these credentials.

The overlay includes `config/cors.php`, which permits only local development origins (`localhost:5173`, `127.0.0.1:5173`) by default. If you use another local dev origin, configure `FRONTEND_ALLOWED_ORIGINS` as a comma-separated allowlist in Laravel's environment, then clear Laravel's config cache. Cross-origin mode does **not** use credentialed cookies.

The overlay also adds a read-only scoped endpoint: `GET /api/v1/purchase-requests/{purchaseRequest}/approval-steps`, and makes existing quote comparisons viewable to any authorized viewer of the purchase request (generating a comparison remains procurement/admin only). Apply these backend patches to use the UI's approval history in API mode.

## Quality gates

```bash
npm run typecheck
npm run test
npm run build
npm run preview
```

In a development environment with internet access, `npm install` generates `package-lock.json`. **Commit that lockfile** and use `npm ci` for subsequent local and CI installs. An exact dependency lockfile is not bundled because the build environment where the overlay was authored could not reach the npm registry; do not claim that the frontend production build has passed until you execute these commands.

The `frontend.yml` GitHub Actions workflow installs dependencies, runs type checks, unit tests, and a production build. It is triggered on PRs/changes to the frontend or related backend routes/controllers.

## Publish free on Vercel (public portfolio demo)

1. Push the overlaid repository to GitHub and ensure the frontend build above succeeds.
2. In Vercel choose the existing repository with **Root Directory: `frontend`**.
3. Framework preset **Vite**; build command `npm run build`, output directory `dist`.
4. Set environment variable **`VITE_DATA_MODE=demo`** (recommended, or leave it unset).
5. Deploy. `frontend/vercel.json` includes an SPA fallback and basic response security headers.

Do **not** set `VITE_DATA_MODE=api` on your public demo unless you independently deploy and secure an HTTPS Laravel backend, configure appropriate CORS and endpoint access, and review the CSP/headers. Vercel does not magically host your Laravel queues, Redis, MySQL, or Outbox.

## Architecture

```
frontend/src/
  app/         session provider, layout, router, navigation
  components/  reusable accessible presentation primitives
  domain/      DTOs, formatting, client visibility helpers
  features/    business pages / forms / workflow UI
  services/    repository facade, API transport, isolated demo adapter
  styles/      responsive design tokens and components
```

The Laravel backend continues to use MVC + thin controllers, request validation, services, policies, and resources. The React app **is not MVC**; it follows feature-oriented UI + domain/service boundaries. The PHP approval read controller authorizes via `PurchaseRequestPolicy::view`, eager-loads approvers, orders steps by sequence, and delegates all writes to existing services.

## Demonstrated functionality

- Role-oriented sign-in; role-switching in synthetic demo; read-only visitor personas.
- Requests index with server-side search/filter and pagination; request form validated via Zod and React Hook Form; request details; draft submission.
- Supplier directory, creation and validation.
- Quote creation, comparison, selected vendor indicator, server-permission-aware approval sequence and decisions (with rationale).
- Invoice register, creation and marked-paid state; activity log.
- Real API errors surfaced to the user; guarded actions; empty/loading/error states; responsive mobile navigation; modal Escape/keyboard handling.

**Important coverage boundary:** the interface handles the major procurement journey but does not yet include CRUD for departments, editing/deleting existing requests, document attachment uploads, detailed individual quote-analysis screens, or a production analytics endpoint. The mock comparison uses the lowest price solely for demo illustration, whereas the real backend uses a weighted deterministic model; the UI explicitly labels this. Server-side pagination is implemented for requests and vendors; the dashboard and approvals summary are intentionally limited to a 100-row sample (not all-time global metrics). Do not describe those summaries as fully aggregated company KPIs.

## Security/operational boundaries

- Public demo has no privileged secrets or production records. All demo accounts, contact addresses and amounts are fictional. Demo localStorage contains *synthetic data only*, never authentication tokens.
- UI hides inappropriate controls for convenience; this is **not** security. Laravel policies, validation, tenant isolation, and state checks are authoritative in API mode.
- No external fonts/assets are fetched by the frontend; this is privacy friendly and avoids a runtime CDN dependency.
- This is not deployed Laravel, and no live cluster monitoring, backup/restore, or external availability claims are made by the frontend.

## v18: Browser QA + route-level bundles

The demo now has a Playwright E2E suite and lazy-loaded feature modules. From `frontend/` run:

```bash
npm install
npx playwright install chromium
npm run typecheck
npm run test
npm run build
npm run test:e2e
```

The build prints the initial JS and per-route chunk measurements; compare against the previous 583.09 kB / 181.43 kB gzip single JS bundle. Browser tests use isolated demo records; they **do not validate Laravel backend authorization**. See [`docs/E2E_AND_BUNDLE_QA.md`](docs/E2E_AND_BUNDLE_QA.md).
