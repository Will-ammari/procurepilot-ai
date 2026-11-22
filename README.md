# ProcurePilot AI

**Procurement workflows with explicit authorization, reliable event delivery, and testable business rules.**

[![Laravel CI](https://github.com/Will-ammari/procurepilot-ai/actions/workflows/ci.yml/badge.svg)](https://github.com/Will-ammari/procurepilot-ai/actions/workflows/ci.yml)
[![MySQL Concurrency & Outbox Reliability](https://github.com/Will-ammari/procurepilot-ai/actions/workflows/mysql-approval-concurrency.yml/badge.svg)](https://github.com/Will-ammari/procurepilot-ai/actions/workflows/mysql-approval-concurrency.yml)
[![React frontend checks](https://github.com/Will-ammari/procurepilot-ai/actions/workflows/frontend.yml/badge.svg)](https://github.com/Will-ammari/procurepilot-ai/actions/workflows/frontend.yml)

**[Try the interactive demo](https://procurepilot-ai-nine.vercel.app/)** · **[Engineering case study](docs/engineering-case-study.md)** · **[Lighthouse report & methodology](docs/performance-lighthouse.md)** · **[API specification](docs/openapi.yaml)**

> **Deployment boundary:** the public Vercel site is a **React/TypeScript simulated demo** with synthetic browser-local data. Laravel, MySQL, Redis, the queue worker, and FastAPI are implemented in this repository and covered by separate tests, **but are not hosted behind the public demo**. The word “AI” in the name does not imply that the public demo calls a live AI model.

![ProcurePilot AI login preview from a user-provided PageSpeed screenshot](docs/assets/demo-login-preview.png)

*Login-page preview cropped from the supplied PageSpeed screenshot (not a full-resolution application capture). Open the [live demo](https://procurepilot-ai-nine.vercel.app/) for the full UI.*

## At a glance

| Area | What is implemented | Evidence |
| --- | --- | --- |
| Procurement | Requests, line items, supplier quotes, deterministic quote comparison, sequential approvals, invoices, VAT, vendor scorecards, activity log | [`app/Services/Procurement/`](app/Services/Procurement/), [`routes/api.php`](routes/api.php) |
| Multi-tenancy | Organization-scoped queries, policies, request validation, cross-tenant tests | [`PurchaseRequestPolicy.php`](app/Policies/PurchaseRequestPolicy.php), [`TenantIsolationTest.php`](tests/Feature/Procurement/TenantIsolationTest.php) |
| Reliable events | DB-atomic outbox write, claim/lease, retry and terminal-failure handling; idempotent consumer | [`OutboxPublisher.php`](app/Services/Outbox/OutboxPublisher.php), [`OutboxReliabilityTest.php`](tests/Feature/Operations/OutboxReliabilityTest.php) |
| Concurrency | Ordered row locks, state revalidation, separate-process MySQL collision tests | [`ApprovalWorkflowService.php`](app/Services/Procurement/ApprovalWorkflowService.php), [`MySqlApprovalConcurrencyTest.php`](tests/Feature/Procurement/MySqlApprovalConcurrencyTest.php) |
| Frontend | Responsive React + TypeScript + Vite, role-based demo, route splitting, session restoration, keyboard/a11y checks | [`frontend/`](frontend/), [`frontend/e2e/`](frontend/e2e/) |
| Operations | Request correlation IDs, health endpoint, bounded application metrics, outbox maintenance commands | [`app/Services/Monitoring/`](app/Services/Monitoring/), [`app/Console/Commands/`](app/Console/Commands/) |

## What problem does it solve?

Procurement processes scattered across emails and spreadsheets are hard to audit. ProcurePilot models the full flow: employee request → sourcing and supplier quotations → deterministic comparison → approval → invoice and vendor performance. The backend exercises business invariants beyond basic CRUD; the publicly accessible UI lets reviewers explore a **synthetic** version without credentials or infrastructure cost.

## Architecture

```mermaid
flowchart TB
    Reviewer[Portfolio reviewer] --> Demo[React + TypeScript SPA — Vercel]
    Demo --> Sim[(Browser-local synthetic demo store)]
    Local[Local API client / integration tests] --> API[Laravel 12 — Sanctum API]
    API --> Policies[Policies + Form Requests + Services]
    Policies --> MySQL[(MySQL)]
    Policies --> FastAPI[Optional FastAPI heuristic quote analysis]
    MySQL --> Outbox[(Outbox messages — same DB transaction)]
    Outbox --> Publisher[Outbox publisher — claim/lease/retry]
    Publisher --> Queue[Redis-backed queue in Docker setup]
    Queue --> Worker[Idempotent audit consumer]
    API --> Ops[Health + request IDs + bounded metrics]
    classDef demo fill:#e7f0fd,stroke:#5687c4,color:#16325b;
    class Demo,Sim demo;
```

**Important:** the blue demo branch and the Laravel API branch above are **not connected in the current public deployment**. Frontend API mode is a separate integration path, not a production service claim. [Read the architectural trade-offs →](docs/engineering-case-study.md).

## Explore the demo

1. Open **[Live Demo](https://procurepilot-ai-nine.vercel.app/)**; choose an available synthetic role and enter the workspace.
2. Create a purchase request, add quotes, compare suppliers, and step through approvals.
3. Try Finance or Viewer to explore role-specific visibility; try refresh on a request detail URL.
4. Use the **Reset Demo** action to clear locally changed synthetic records.
5. Read the public **[Engineering page](https://procurepilot-ai-nine.vercel.app/engineering)** and the deeper [source-backed case study](docs/engineering-case-study.md).

Demo permissions are UI simulation only. **Authorization claims concern the Laravel policies and feature tests, not client-side role controls.** No real procurement, payment, or customer data is processed by the hosted demo.

## Technology

- **Backend:** PHP 8.2+, Laravel 12, Sanctum, Eloquent, MySQL 8.x, Redis/queues, PHPUnit, Larastan/PHPStan, Laravel Pint.
- **Frontend:** React 19, TypeScript, Vite 6, React Router, TanStack Query, React Hook Form, Zod; Vitest, Playwright, axe, ESLint, Prettier.
- **Integration:** optional FastAPI quote-analysis sidecar with deterministic mock/fallback behavior; not an authenticated live LLM showcase.
- **Local operations:** Docker Compose (Nginx, PHP, MySQL, Redis, worker, Mailpit and FastAPI), GitHub Actions, Vercel static frontend.

## Run the frontend demo locally

**Node.js 22** (matches frontend CI) and npm:

```bash
cd frontend
npm ci
# VITE_DATA_MODE=demo is the intended public demo configuration.
# PowerShell: $env:VITE_DATA_MODE = 'demo'
# POSIX: VITE_DATA_MODE=demo npm run dev
npm run dev
```

Set `VITE_DATA_MODE=demo` in your shell before running `npm run dev`. The UI is served at the Vite address printed in your terminal (typically `http://127.0.0.1:5173`).

Frontend quality gates:

```bash
cd frontend
npm run typecheck
npm run lint
npm run format:check
npm run test
npm run test:tooling
npm run build
npx playwright install chromium  # first run only
npm run test:e2e
npm audit
```

## Run the Laravel backend locally

**Option A — Docker Compose**, with Docker installed:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

In this Compose setup the API is exposed at `http://localhost:8000` and the FastAPI service at `http://localhost:8001`. See [`docs/PHASE3_RUNBOOK.md`](docs/PHASE3_RUNBOOK.md) for outbox/worker operations and [`docs/procurepilot-architecture.md`](docs/procurepilot-architecture.md) for further architectural background.

**Option B — native PHP + MySQL:**

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configure DB credentials and required local services in .env.
php artisan migrate --seed
php artisan serve
```

Use your platform's equivalent of `cp` on Windows (`Copy-Item .env.example .env`). This does not automatically start workers or FastAPI. Never commit `.env`.

Backend validation (independent of the frontend demo):

```bash
composer lint
composer analyse
php artisan test
```

**Real MySQL concurrency tests are separate and destructive to their dedicated test database.** They run in the GitHub Actions [`mysql-approval-concurrency.yml`](.github/workflows/mysql-approval-concurrency.yml) job; do **not** point `phpunit.mysql.xml` at any valuable database. See [the operations/concurrency runbook](docs/reliability-and-performance.md) for the required isolated test setup.

## Evidence, measurements and limits

| Evidence | Recorded outcome | Caveat |
| --- | --- | --- |
| Frontend Playwright | **30/30 passed** (15 desktop + 15 mobile, user-run on Nov 19, 2026) | Synthetic demo; not full cross-browser certification |
| Frontend Vitest | **11/11 passed** (user-run on v19.1) | Unit scope only |
| Tooling tests | **10/10 passed** (user-run on v19.1) | Includes contrast-token checks, not full WCAG certification |
| TypeScript / ESLint / Prettier / Vite build | Passed (user-provided PowerShell logs) | Local checks, CI status can change |
| PageSpeed Insights on **landing/login** | **Mobile & desktop: 100 Performance, 100 Accessibility, 100 Best Practices, 91 SEO** | Single captured lab runs, not measured user sessions or dashboard performance |
| Agentic Browsing (experimental) | **2/4** in supplied report, with missing/invalid discovery files | This update supplies `llms.txt` and a valid empty ARD manifest; re-test after deploy |
| Laravel CI and isolated MySQL concurrency | CI workflows and tests in repository | Not proof of running public backend production service |

The uploaded PageSpeed desktop screenshot is archived at [`docs/assets/pagespeed-desktop-2026-11-19.png`](docs/assets/pagespeed-desktop-2026-11-19.png). [Measurement methodology and follow-up checklist →](docs/performance-lighthouse.md).

## Technical reading & project map

- **[Engineering case study](docs/engineering-case-study.md):** failures, invariants, lock ordering, outbox and idempotency, tenant boundaries, AI limitations.
- **[Architecture overview](docs/procurepilot-architecture.md):** domain entities and backend structure.
- **[Operational runbook](docs/PHASE3_RUNBOOK.md):** outbox/worker commands and failure handling.
- **[Performance / Lighthouse evidence](docs/performance-lighthouse.md):** mobile + desktop scores and limitations.
- **[OpenAPI spec](docs/openapi.yaml)** and **[Postman collection](docs/postman_collection.json)**.
- **[Frontend integration](docs/frontend-integration.md)** and **[frontend QA](frontend/docs/E2E_AND_BUNDLE_QA.md)**.

## Honest project status

**Implemented and verifiable in source:** backend domain workflows, tenant-aware API code, reliable-event mechanisms, tests/CI, standalone UI demo and frontend accessibility checks. **Not claimed:** live Laravel/Redis/MySQL hosting, customer production usage, real-user Web Vitals, a trained AI model, comprehensive WCAG/security audit, or production SLOs/incident response. For a portfolio these are deliberately separate from the product demonstration.

MIT-licensed Laravel skeleton dependencies and third-party packages retain their respective licenses. See dependency manifests and license files.


### v21 — Local operational visibility and security preflight

A **local-only** Prometheus/Grafana pipeline exports real Laravel/Outbox/Queue gauges through an internal Docker network without a public metrics API. The API now adds security headers to final responses; Docker development ports bind to loopback. These changes are **not** a production deployment or external security certification. See [v21 runbook](docs/V21_OBSERVABILITY_SECURITY_RUNBOOK.md) and [threat model](docs/V21_SECURITY_THREAT_MODEL.md) for setup, reproducible failure drills, rollback and unimplemented production controls.
