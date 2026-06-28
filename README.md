# ProcurePilot AI

**ProcurePilot AI** is a production-style Laravel API backend for AI-assisted procurement management, designed for small and medium-sized companies in the German and European market.

The project models a complete procurement workflow: employees create purchase requests, procurement teams collect supplier quotes, the system analyzes and compares offers, approval steps are generated based on budget thresholds, finance tracks invoices and VAT, and vendor performance is calculated through scorecards.

> Current test status: **77 tests passed / 269 assertions**

---

## Table of Contents

- [Problem](#problem)
- [Solution](#solution)
- [Core Features](#core-features)
- [Architecture](#architecture)
- [Tech Stack](#tech-stack)
- [Procurement Workflow](#procurement-workflow)
- [Roles and Permissions](#roles-and-permissions)
- [AI Design](#ai-design)
- [Database Model](#database-model)
- [API Overview](#api-overview)
- [Activity Logs](#activity-logs)
- [Security and Authorization](#security-and-authorization)
- [Testing](#testing)
- [Local Setup](#local-setup)
- [Demo Credentials](#demo-credentials)
- [Project Status](#project-status)
- [Roadmap](#roadmap)
- [Portfolio Context](#portfolio-context)

---

## Problem

Procurement workflows in small and medium-sized companies are often fragmented across emails, spreadsheets, PDFs, and manual approvals. This creates problems such as:

- poor visibility over purchase requests,
- inconsistent supplier evaluation,
- missing approval traceability,
- manual quote comparison,
- invoice and VAT tracking mistakes,
- weak supplier performance history.

ProcurePilot AI addresses these problems with a structured, API-first procurement backend built around clean business workflows, multi-tenant isolation, authorization policies, automated quote analysis, deterministic comparison logic, and audit-ready activity logs.

---

## Solution

ProcurePilot AI provides a backend system where each organization can manage its procurement process independently:

1. A requester creates a purchase request with line items.
2. Procurement adds supplier quotes.
3. The system analyzes quote terms and hidden costs.
4. The system compares quotes using deterministic scoring.
5. Procurement sends the selected offer for approval.
6. Managers, finance, and admins approve based on budget thresholds.
7. Finance creates invoices and tracks VAT.
8. Vendor scorecards are calculated from quotes and invoices.
9. Activity logs capture important business events.

The goal is not just to build CRUD endpoints, but to demonstrate a realistic Laravel SaaS backend with maintainable architecture and production-style patterns.

---

## Core Features

### Authentication

- Laravel Sanctum bearer token authentication.
- Login, current user, and logout endpoints.
- API routes under `/api/v1`.

### Multi-Tenant SaaS Foundation

- Organization-scoped resources.
- Users belong to organizations and optionally departments.
- All major records are isolated by `organization_id`.
- Cross-organization access is blocked through policies and service-level validation.

### Departments

- Admin-managed departments.
- Department listing and filtering.
- Unique department names per organization.
- Protection against cross-organization access.

### Vendors

- Vendor management with contacts.
- Vendor filtering by status and search terms.
- Requesters can see active vendors only.
- Admin and procurement users can create and update vendors.
- Blocked vendors cannot be used for new quotes.

### Purchase Requests

- Requesters can create purchase requests with items.
- At least one item is required during creation.
- Draft requests can be updated.
- Draft requests can be submitted.
- Submitted requests cannot be edited by requesters.
- Requesters see their own requests.
- Managers see department requests.
- Procurement sees organization-wide requests.

### Quotes

- Procurement can add quotes to submitted purchase requests.
- Quotes contain supplier information, payment terms, delivery days, warranty, and quote items.
- Quote items can be replaced during update.
- Quote access is organization-scoped.

### AI Quote Analysis

- Deterministic local analysis implementation for MVP reliability.
- Extracts summary, hidden cost indicators, risk notes, recommendation notes, and confidence score.
- Designed to be replaced or extended by a FastAPI AI microservice later.

### Quote Comparison

- Generates quote comparison for a purchase request.
- Requires at least two quotes.
- Uses deterministic scoring rather than uncontrolled LLM decision-making.
- Stores comparison results.
- Selects a recommended quote based on weighted scoring.

Scoring weights:

| Factor | Weight |
|---|---:|
| Total amount | 35% |
| Delivery days | 20% |
| Payment terms | 15% |
| Warranty months | 10% |
| Hidden costs | 10% |
| Vendor status | 10% |

### Approval Workflow

- Procurement/Admin can send purchase requests for approval.
- Requires a generated comparison and recommended quote.
- Approval steps are generated based on estimated budget.
- Sequential approval is enforced.
- Requesters cannot approve their own purchase requests.
- Rejections require comments.

Approval thresholds:

| Estimated Budget | Approval Chain |
|---:|---|
| Below 1,000 EUR | Department Manager |
| 1,000 - 10,000 EUR | Department Manager + Finance |
| Above 10,000 EUR | Department Manager + Finance + Admin |

### Invoices and VAT

- Finance/Admin can create invoices.
- Invoices are linked to approved, ordered, or invoiced purchase requests.
- Vendor consistency is validated against the approved quote.
- VAT is calculated automatically.
- Default organization VAT can be used; custom VAT rate is supported.
- Paid invoices cannot be edited.
- Marking an invoice as paid updates the related purchase request.

### Vendor Scorecards

- Calculates vendor performance metrics.
- Tracks total quotes, accepted quotes, win rate, average delivery days, invoice issues, paid invoices, total invoiced amount, and overall score.
- Blocked vendors receive an overall score of zero.
- Scorecards are generated or updated on demand.

### Activity Logs

- Audit trail for major business events.
- Organization-scoped activity log records.
- Supports user, event, subject, and metadata tracking.
- Filterable API for procurement, finance, and admin users.

---

## Architecture

The project follows an API-first Laravel architecture with a clean separation of responsibilities.

```text
app/
├── Http/
│   ├── Controllers/Api/V1/   # Thin controllers for HTTP orchestration
│   ├── Requests/Api/V1/      # Form request validation
│   └── Resources/Api/V1/     # Consistent JSON API responses
├── Models/                   # Eloquent models, relationships, casts, constants
├── Policies/                 # Authorization and tenant isolation rules
└── Services/
    ├── AI/                   # Quote analysis service
    ├── Procurement/          # Procurement business workflows
    └── Support/              # Shared supporting services
```

Architectural principles:

- Controllers stay thin.
- Business logic lives in services.
- Validation lives in Form Requests.
- JSON output is normalized through Resources.
- Authorization is enforced through Policies.
- Complex writes are wrapped in database transactions.
- Organization scope is derived from the authenticated user, not trusted from request bodies.
- Feature tests cover business rules, permissions, and cross-tenant isolation.

---

## Tech Stack

- PHP
- Laravel
- Laravel Sanctum
- MySQL
- Eloquent ORM
- PHPUnit / Laravel Feature Tests
- API-first backend design

Planned additions:

- Docker
- Redis queue support
- FastAPI AI microservice
- OpenAPI documentation
- Postman collection
- GitHub Actions CI

---

## Procurement Workflow

```text
Requester creates purchase request
        ↓
Requester submits purchase request
        ↓
Procurement adds supplier quotes
        ↓
System analyzes quote terms
        ↓
System compares quotes and recommends best offer
        ↓
Procurement sends request for approval
        ↓
Manager / Finance / Admin approve based on threshold
        ↓
Finance creates invoice
        ↓
Finance marks invoice as paid
        ↓
System calculates vendor scorecard
        ↓
Activity logs provide audit trail
```

---

## Roles and Permissions

| Role | Main Capabilities |
|---|---|
| Admin | Full organization-level management, including departments, vendors, approvals, invoices, and scorecards |
| Procurement Officer | Manage vendors, quotes, comparisons, and approval submission |
| Requester | Create and submit own purchase requests, view active vendors and own request analysis |
| Department Manager | View department purchase requests and approve relevant approval steps |
| Finance Manager | Handle invoice workflow and finance approval steps |
| Viewer | Read-only access where allowed |

Authorization is enforced through Laravel Policies and tested with feature tests.

---

## AI Design

The current AI component is implemented as a deterministic local Laravel service. This keeps the MVP testable, stable, and independent from external AI provider availability.

Current behavior:

- analyzes submitted quote data,
- identifies hidden cost indicators,
- extracts risk and recommendation notes,
- stores confidence score,
- supports regeneration,
- avoids automatic approval or rejection decisions.

Future AI architecture:

```text
Laravel API
   ↓
QuoteAnalysisJob
   ↓
AI Client
   ↓
FastAPI Service
   ↓
Mock Provider / OpenAI-Compatible Provider
```

The Laravel backend remains the system of record. The FastAPI service will only handle extraction, summarization, and analysis tasks.

---

## Database Model

Main tables currently covered by the backend:

- `organizations`
- `departments`
- `users`
- `vendors`
- `vendor_contacts`
- `purchase_requests`
- `purchase_request_items`
- `quotes`
- `quote_items`
- `quote_analyses`
- `quote_comparisons`
- `approval_steps`
- `invoices`
- `vendor_scorecards`
- `activity_logs`
- `personal_access_tokens`

---

## API Overview

Base prefix:

```http
/api/v1
```

### Auth

```http
POST /api/v1/auth/login
GET  /api/v1/me
POST /api/v1/auth/logout
```

### Departments

```http
GET    /api/v1/departments
POST   /api/v1/departments
GET    /api/v1/departments/{department}
PATCH  /api/v1/departments/{department}
DELETE /api/v1/departments/{department}
```

### Vendors

```http
GET    /api/v1/vendors
POST   /api/v1/vendors
GET    /api/v1/vendors/{vendor}
PATCH  /api/v1/vendors/{vendor}
DELETE /api/v1/vendors/{vendor}
GET    /api/v1/vendors/{vendor}/scorecard
```

### Purchase Requests

```http
GET    /api/v1/purchase-requests
POST   /api/v1/purchase-requests
GET    /api/v1/purchase-requests/{purchaseRequest}
PATCH  /api/v1/purchase-requests/{purchaseRequest}
DELETE /api/v1/purchase-requests/{purchaseRequest}
POST   /api/v1/purchase-requests/{purchaseRequest}/submit
GET    /api/v1/purchase-requests/{purchaseRequest}/comparison
POST   /api/v1/purchase-requests/{purchaseRequest}/send-for-approval
```

### Quotes

```http
GET   /api/v1/purchase-requests/{purchaseRequest}/quotes
POST  /api/v1/purchase-requests/{purchaseRequest}/quotes
GET   /api/v1/quotes/{quote}
PATCH /api/v1/quotes/{quote}
POST  /api/v1/quotes/{quote}/analyze
GET   /api/v1/quotes/{quote}/analysis
```

### Approval Workflow

```http
POST /api/v1/approval-steps/{approvalStep}/approve
POST /api/v1/approval-steps/{approvalStep}/reject
```

### Invoices

```http
GET   /api/v1/invoices
POST  /api/v1/invoices
GET   /api/v1/invoices/{invoice}
PATCH /api/v1/invoices/{invoice}
PATCH /api/v1/invoices/{invoice}/mark-paid
```

### Activity Logs

```http
GET /api/v1/activity-logs
GET /api/v1/activity-logs/{activityLog}
```

Supported filters:

```http
GET /api/v1/activity-logs?event=purchase_request.created
GET /api/v1/activity-logs?user_id=1
GET /api/v1/activity-logs?subject_type=App\Models\PurchaseRequest&subject_id=1
GET /api/v1/activity-logs?from=2026-06-01&to=2026-06-30
```

---

## Activity Logs

The activity log module captures auditable business events such as:

- `purchase_request.created`
- `purchase_request.submitted`
- `quote.created`
- `quote.analysis_completed`
- `comparison.generated`
- `approval.approved`
- `approval.rejected`
- `invoice.received`
- `invoice.paid`
- `vendor_scorecard.calculated`

Each log record stores:

- organization,
- user,
- event name,
- polymorphic subject,
- metadata,
- IP address,
- user agent,
- timestamps.

This makes the system more suitable for compliance-heavy procurement workflows.

---

## Security and Authorization

Security design:

- Sanctum bearer tokens protect all application endpoints except login.
- Policies enforce role-based access control.
- Every major business resource is scoped by `organization_id`.
- Request bodies are not trusted for tenant ownership.
- Cross-organization access is tested and blocked.
- Requesters cannot approve their own purchase requests.
- Blocked vendors cannot be used for quote creation.
- Paid invoices cannot be modified.

---

## Testing

Current test result:

```text
Tests: 77 passed (269 assertions)
```

Covered areas:

- authentication-related protected routes,
- department management,
- vendor management,
- purchase request creation and submission,
- quote creation and updates,
- quote analysis,
- quote comparison,
- approval workflow,
- invoices and VAT,
- vendor scorecards,
- activity logs,
- authorization rules,
- cross-organization isolation,
- validation errors.

Run tests:

```bash
php artisan test
```

Useful verification commands:

```bash
php artisan migrate:status
php artisan route:list
php artisan test
```

---

## Local Setup

### Requirements

- PHP 8.2+
- Composer
- MySQL
- Node.js and npm if frontend assets are needed by the Laravel skeleton

### Installation

```bash
git clone https://github.com/Will-ammari/procurepilot-ai.git
cd procurepilot-ai
composer install
cp .env.example .env
php artisan key:generate
```

Configure database values in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=procurepilot
DB_USERNAME=root
DB_PASSWORD=
```

Run migrations and seeders:

```bash
php artisan migrate --seed
```

Start the API:

```bash
php artisan serve
```

Run tests:

```bash
php artisan test
```

---

## Demo Credentials

If the demo seeder is enabled, the intended demo users are:

| Role | Email | Password |
|---|---|---|
| Admin | `admin@procurepilot.test` | `password` |
| Requester | `requester@procurepilot.test` | `password` |
| Procurement Officer | `procurement@procurepilot.test` | `password` |
| Department Manager | `manager@procurepilot.test` | `password` |
| Finance Manager | `finance@procurepilot.test` | `password` |
| Viewer | `viewer@procurepilot.test` | `password` |

Login example:

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@procurepilot.test",
  "password": "password"
}
```

Use the returned token as a bearer token:

```http
Authorization: Bearer {token}
```

---

## Project Status

Completed:

- Laravel API backend
- Sanctum authentication
- Multi-tenant organization scoping
- Roles and policies
- Departments API
- Vendors and vendor contacts API
- Purchase requests and items API
- Quotes and quote items API
- Deterministic quote analysis
- Quote comparison and recommendation scoring
- Approval workflow
- Invoices and VAT calculation
- Vendor scorecard API
- Activity logs API
- Feature test coverage

In progress / planned:

- OpenAPI documentation
- Postman collection
- Docker setup
- GitHub Actions CI
- FastAPI AI microservice
- Attachments and file uploads
- Screenshots and demo script

---

## Roadmap

### Next Milestone: API Documentation

- Add `docs/openapi.yaml`.
- Add `docs/postman_collection.json`.
- Document authentication, payloads, responses, and errors.

### DevOps Milestone

- Add `Dockerfile`.
- Add `docker-compose.yml` with app, MySQL, Redis, and optional Mailpit.
- Add GitHub Actions workflow for automated tests.

### AI Service Milestone

- Add FastAPI service.
- Add `/analyze-quote` endpoint.
- Add mock provider.
- Add OpenAI-compatible provider interface.
- Add Laravel AI client.
- Add queued `QuoteAnalysisJob`.

### Product Polish Milestone

- Add quote and invoice attachments.
- Add screenshots.
- Add demo walkthrough.
- Add architecture diagram.

---

## Portfolio Context

This project is designed as a backend portfolio project for Laravel SaaS and AI-assisted business workflows.

It demonstrates:

- production-style Laravel backend architecture,
- multi-tenant SaaS data modeling,
- role-based authorization,
- service-layer business logic,
- API-first development,
- procurement workflow modeling,
- deterministic AI-assisted decision support,
- test-driven confidence for business rules,
- auditability through activity logs.

The project is intentionally focused on backend depth rather than frontend UI. It is suitable for showcasing Laravel, SaaS architecture, clean code, and business workflow engineering.

---

## License

This project is currently intended as a portfolio and educational project. Add a license file before using it for commercial distribution.
