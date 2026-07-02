# ProcurePilot AI

![Laravel CI](https://github.com/Will-ammari/procurepilot-ai/actions/workflows/ci.yml/badge.svg)

**ProcurePilot AI** is a production-style Laravel API backend for AI-assisted procurement management, designed for small and medium-sized companies in the German and European market.

The project models a complete procurement workflow: employees create purchase requests, procurement teams collect supplier quotes, the system analyzes and compares offers, approval steps are generated based on budget thresholds, finance tracks invoices and VAT, and vendor performance is calculated through scorecards.

> Current quality status: **Laravel Pint passing / PHPStan no errors / 101 tests passed / 367 assertions**

---

## Hiring Signal

| Area | What this project proves |
| --- | --- |
| Backend architecture | API-first Laravel backend with thin controllers, Form Requests, Resources, Services, Policies, and queued Jobs. |
| SaaS readiness | Organization-level tenant isolation, role-based authorization, request-derived ownership, and cross-tenant protection. |
| Production operations | Health endpoint with database, cache, Redis, and queue checks; request IDs; standardized API errors; Docker queue worker. |
| Quality pipeline | PHPUnit feature tests, Laravel Pint code style checks, Larastan/PHPStan static analysis, and GitHub Actions CI. |
| Business workflows | Procurement requests, supplier quotes, quote comparison, approvals, invoices, VAT, vendor scorecards, and audit logs. |
| Maintainability | Service-layer business logic, reusable factories/test scenarios, documented architecture, and deterministic test behavior. |

## Table of Contents

* [Problem](#problem)
* [Solution](#solution)
* [Production Readiness](#production-readiness)
* [Core Features](#core-features)
* [Architecture](#architecture)
* [Tech Stack](#tech-stack)
* [Procurement Workflow](#procurement-workflow)
* [Roles and Permissions](#roles-and-permissions)
* [AI Design](#ai-design)
* [Database Model](#database-model)
* [API Overview](#api-overview)
* [API Documentation](#api-documentation)
* [Activity Logs](#activity-logs)
* [Security and Authorization](#security-and-authorization)
* [Testing](#testing)
* [Local Setup](#local-setup)
* [Docker Setup](#docker-setup)
* [Demo Credentials](#demo-credentials)
* [Project Status](#project-status)
* [Roadmap](#roadmap)
* [Portfolio Context](#portfolio-context)
* [License](#license)

---

## Problem

Procurement workflows in small and medium-sized companies are often fragmented across emails, spreadsheets, PDFs, and manual approvals. This creates several operational risks:

* poor visibility over purchase requests,
* inconsistent supplier evaluation,
* missing approval traceability,
* manual quote comparison,
* invoice and VAT tracking mistakes,
* weak supplier performance history,
* limited auditability for procurement decisions.

**ProcurePilot AI** addresses these problems with a structured, API-first procurement backend built around clean business workflows, multi-tenant isolation, authorization policies, automated quote analysis, deterministic comparison logic, and audit-ready activity logs.

---

## Solution

ProcurePilot AI provides a backend system where each organization can manage its procurement process independently.

The main workflow:

1. A requester creates a purchase request with line items.
2. Procurement collects supplier quotes.
3. The system analyzes quote terms and hidden cost indicators.
4. The system compares quotes using deterministic scoring.
5. Procurement sends the selected offer for approval.
6. Managers, finance, and admins approve based on budget thresholds.
7. Finance creates invoices and tracks VAT.
8. Vendor scorecards are calculated from quotes and invoices.
9. Activity logs capture important business events.

The goal is not just to build CRUD endpoints, but to demonstrate a realistic Laravel SaaS backend with maintainable architecture, strong authorization, automated tests, Dockerized development, CI, and an AI microservice integration.

---

## Production Readiness

This repository includes production-oriented backend patterns that demonstrate maintainability, testability, and operational awareness:

* Organization-level tenant isolation with dedicated feature tests.
* Role-based authorization covered by policy-focused tests.
* Invoice and billing workflow validation, including VAT and paid-invoice protection.
* Asynchronous queue processing for submitted purchase requests.
* Reusable factories and test scenario builders for maintainable test setup.
* Docker-based local environment with MySQL, Redis, Nginx, Mailpit, queue worker, and FastAPI sidecar.
* GitHub Actions CI pipeline for code style, static analysis, and automated test execution.
* Architecture documentation for reviewers and technical interviewers.
* Health monitoring endpoint for database, cache, Redis, and queue dependency checks.
* Request ID propagation through `X-Request-Id` for easier debugging and log correlation.
* Standardized JSON API error responses for validation, authentication, authorization, not found, rate limit, and server errors.

Latest Laravel quality check:

```text
Laravel Pint: PASS
PHPStan/Larastan: No errors
Tests: 97 passed (359 assertions)
```

---

## Core Features

### Authentication

* Laravel Sanctum bearer token authentication.
* Login, current user, and logout endpoints.
* Protected API routes under `/api/v1`.

### Multi-Tenant SaaS Foundation

* Organization-scoped resources.
* Users belong to organizations and optionally departments.
* All major records are isolated by `organization_id`.
* Cross-organization access is blocked through policies and service-level validation.
* Request bodies are not trusted for tenant ownership.

### Departments

* Admin-managed departments.
* Department listing and filtering.
* Unique department names per organization.
* Protection against cross-organization access.
* Role-based create/update/delete authorization.

### Vendors

* Vendor management with contacts.
* Vendor filtering by status and search terms.
* Requesters can see active vendors only.
* Admin and procurement users can create and update vendors.
* Blocked vendors cannot be used for new quotes.
* Vendor scorecards are generated on demand.

### Purchase Requests

* Requesters can create purchase requests with items.
* At least one item is required during creation.
* Draft requests can be updated.
* Draft requests can be submitted.
* Submitted requests cannot be edited by requesters.
* Requesters see their own requests.
* Managers see department requests.
* Procurement sees organization-wide requests.

### Quotes

* Procurement can add quotes to submitted purchase requests.
* Quotes contain supplier information, payment terms, delivery days, warranty, and quote items.
* Quote items can be replaced during update.
* Blocked vendors cannot be used for quote creation.
* Quote access is organization-scoped.

### AI Quote Analysis

* Hybrid analysis architecture:

  * local deterministic Laravel analyzer,
  * external FastAPI AI microservice.
* Extracts summary, hidden cost indicators, risk notes, recommendation notes, and confidence score.
* Supports regeneration.
* Keeps tests deterministic by falling back to local analysis when the AI microservice is disabled.
* Avoids automatic approval or rejection decisions.

### Quote Comparison

* Generates quote comparison for a purchase request.
* Requires at least two quotes.
* Uses deterministic scoring rather than uncontrolled LLM decision-making.
* Stores comparison results.
* Selects a recommended quote based on weighted scoring.

Scoring weights:

| Factor          | Weight |
| --------------- | -----: |
| Total amount    |    35% |
| Delivery days   |    20% |
| Payment terms   |    15% |
| Warranty months |    10% |
| Hidden costs    |    10% |
| Vendor status   |    10% |

### Approval Workflow

* Procurement/Admin can send purchase requests for approval.
* Requires a generated comparison and recommended quote.
* Approval steps are generated based on estimated budget.
* Sequential approval is enforced.
* Requesters cannot approve their own purchase requests.
* Rejections require comments.
* Approved purchase requests receive the recommended quote as the approved quote.

Approval thresholds:

|   Estimated Budget | Approval Chain                       |
| -----------------: | ------------------------------------ |
|    Below 1,000 EUR | Department Manager                   |
| 1,000 - 10,000 EUR | Department Manager + Finance         |
|   Above 10,000 EUR | Department Manager + Finance + Admin |

### Invoices and VAT

* Finance/Admin can create invoices.
* Invoices are linked to approved, ordered, or invoiced purchase requests.
* Vendor consistency is validated against the approved quote.
* VAT is calculated automatically.
* Default organization VAT can be used.
* Custom VAT rate is supported.
* Paid invoices cannot be edited.
* Marking an invoice as paid updates the related purchase request.

### Attachments and File Uploads

* Upload files to purchase requests, quotes, and invoices.
* Store attachment metadata with organization scoping.
* Validate file type and maximum size.
* Support metadata listing, file download, and deletion.
* Enforce role-based authorization through policies.
* Remove stored files when attachments are deleted.

### Vendor Scorecards

* Calculates vendor performance metrics.
* Tracks total quotes, accepted quotes, win rate, average delivery days, invoice issues, paid invoices, total invoiced amount, and overall score.
* Blocked vendors receive an overall score of zero.
* Scorecards are generated or updated on demand.

### Activity Logs

* Audit trail for major business events.
* Organization-scoped activity log records.
* Supports user, event, subject, and metadata tracking.
* Filterable API for procurement, finance, and admin users.


### Health Monitoring

* Public health endpoint under `/api/v1/health`.
* Checks database connectivity.
* Checks cache read/write behavior.
* Checks Redis connectivity when Redis is required by cache or queue configuration.
* Reports queue configuration status.
* Returns latency per dependency check.
* Designed for deployment readiness and basic uptime monitoring.

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
├── Jobs/                     # Asynchronous queue jobs
└── Services/
    ├── AI/                   # Quote analysis and AI client integration
    ├── Monitoring/           # Health checks and operational monitoring
    ├── Procurement/          # Procurement business workflows
    └── Support/              # Shared supporting services, including attachments
```

FastAPI microservice structure:

```text
fastapi-service/
├── app/
│   ├── main.py
│   ├── schemas.py
│   ├── providers/
│   │   └── mock_provider.py
│   └── services/
│       └── quote_analyzer.py
├── tests/
│   └── test_analyze_quote.py
├── Dockerfile
├── pytest.ini
└── requirements.txt
```

Architectural principles:

* Controllers stay thin.
* Business logic lives in services.
* Validation lives in Form Requests.
* JSON output is normalized through Resources.
* Authorization is enforced through Policies.
* Complex writes are wrapped in database transactions.
* Organization scope is derived from the authenticated user.
* Feature tests cover business rules, permissions, billing, health checks, and cross-tenant isolation.
* Queue jobs are used for asynchronous operational side effects.
* Health checks expose dependency status for deployment readiness.
* AI analysis is isolated behind a Laravel client/service boundary.
* The Laravel API remains the system of record.

---

## Tech Stack

### Backend

* PHP 8.3
* Laravel
* Laravel Sanctum
* Eloquent ORM
* MySQL 8.4
* Redis
* PHPUnit / Laravel Feature Tests

### AI Microservice

* Python 3.12
* FastAPI
* Pydantic
* Uvicorn
* Pytest
* HTTP-based Laravel integration

### DevOps and Tooling

* Docker
* Docker Compose
* Nginx
* Mailpit
* GitHub Actions CI
* Laravel Pint
* Larastan / PHPStan
* OpenAPI documentation
* Postman collection

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

| Role                | Main Capabilities                                                                                       |
| ------------------- | ------------------------------------------------------------------------------------------------------- |
| Admin               | Full organization-level management, including departments, vendors, approvals, invoices, and scorecards |
| Procurement Officer | Manage vendors, quotes, comparisons, and approval submission                                            |
| Requester           | Create and submit own purchase requests, view active vendors and own request analysis                   |
| Department Manager  | View department purchase requests and approve relevant approval steps                                   |
| Finance Manager     | Handle invoice workflow and finance approval steps                                                      |
| Viewer              | Read-only access where allowed                                                                          |

Authorization is enforced through Laravel Policies and tested with feature tests.

---

## AI Design

ProcurePilot AI uses a hybrid AI architecture.

The Laravel backend remains the system of record for procurement data, authorization, workflows, and persistence. Quote analysis can be handled either by a local deterministic analyzer or by a separate FastAPI AI microservice.

The local deterministic analyzer is used as a stable fallback and keeps the automated test suite reproducible. The FastAPI service is used in Docker/development environments when enabled through environment variables.

Current behavior:

* analyzes submitted quote data,
* identifies hidden cost indicators,
* extracts risk and recommendation notes,
* stores confidence score,
* supports regeneration,
* avoids automatic approval or rejection decisions,
* falls back to local analysis if the external AI service is unavailable.

AI service flow:

```text
Laravel API
   ↓
QuoteAnalysisService
   ↓
QuoteAnalysisClient
   ↓
FastAPI Service
   ↓
Mock Provider / future OpenAI-Compatible Provider
   ↓
Structured quote analysis response
```

Environment configuration:

```env
AI_SERVICE_ENABLED=true
AI_SERVICE_URL=http://ai-service:8000
AI_SERVICE_TIMEOUT=5
```

The FastAPI service exposes:

```http
GET  /health
POST /analyze-quote
```

Interactive FastAPI documentation is available locally at:

```text
http://localhost:8001/docs
```

In test environments, Laravel uses the local deterministic analyzer by default to keep tests stable and reproducible.

---

## Database Model

Main tables currently covered by the backend:

* `organizations`
* `departments`
* `users`
* `vendors`
* `vendor_contacts`
* `purchase_requests`
* `purchase_request_items`
* `quotes`
* `quote_items`
* `quote_analyses`
* `quote_comparisons`
* `approval_steps`
* `invoices`
* `vendor_scorecards`
* `activity_logs`
* `attachments`
* `personal_access_tokens`

---

## API Overview

Base prefix:

```http
/api/v1
```

### Health

```http
GET /api/v1/health
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

## API Documentation

The project includes API documentation artifacts for review and manual testing:

```text
docs/openapi.yaml
docs/postman_collection.json
```

Recommended usage:

* Use the OpenAPI file to inspect available endpoints, payloads, responses, and authentication requirements.
* Import the Postman collection to test the API manually with bearer token authentication.
* Use demo credentials from the seed data to authenticate and exercise the procurement workflow.

---

## Activity Logs

The activity log module captures auditable business events such as:

* `purchase_request.created`
* `purchase_request.submitted`
* `quote.created`
* `quote.analysis_completed`
* `comparison.generated`
* `approval.approved`
* `approval.rejected`
* `invoice.received`
* `invoice.paid`
* `vendor_scorecard.calculated`

Each log record stores:

* organization,
* user,
* event name,
* polymorphic subject,
* metadata,
* IP address,
* user agent,
* timestamps.

This makes the system more suitable for compliance-heavy procurement workflows.

---

## Security and Authorization

Security design:

* Sanctum bearer tokens protect all application endpoints except login.
* Policies enforce role-based access control.
* Every major business resource is scoped by `organization_id`.
* Request bodies are not trusted for tenant ownership.
* Cross-organization access is tested and blocked.
* Requesters cannot approve their own purchase requests.
* Blocked vendors cannot be used for quote creation.
* Paid invoices cannot be modified.
* AI analysis does not automatically approve or reject supplier offers.

---

## Testing

Current Laravel quality result:

```text
Laravel Pint: PASS
PHPStan/Larastan: No errors
Tests: 97 passed (359 assertions)
```

Current FastAPI test result:

```text
3 passed
```

Covered Laravel areas:

* authentication-related protected routes,
* department management,
* vendor management,
* purchase request creation and submission,
* quote creation and updates,
* quote analysis,
* quote comparison,
* approval workflow,
* invoices and VAT,
* vendor scorecards,
* activity logs,
* authorization rules,
* cross-organization isolation,
* validation errors,
* attachment upload, listing, metadata viewing, deletion, authorization, and file validation,
* health monitoring endpoint,
* production-readiness tests for tenant isolation, authorization policies, and invoice/billing workflows.

Run the full Laravel quality pipeline:

```bash
composer quality
```

Run Laravel tests only:

```bash
php artisan test
```

Run Laravel tests inside Docker:

```bash
docker compose exec app php artisan test
```

Run FastAPI tests locally:

```bash
cd fastapi-service
python -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt
pytest
```

On Windows PowerShell:

```powershell
cd fastapi-service
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
pytest
```

Useful verification commands:

```bash
composer quality
php artisan migrate:status
php artisan route:list
php artisan test
```

---

## Local Setup

### Requirements

* PHP 8.2+
* Composer
* MySQL
* Python 3.12+ for the FastAPI service
* Node.js and npm if frontend assets are needed by the Laravel skeleton

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

Start the Laravel API:

```bash
php artisan serve
```

Run tests:

```bash
php artisan test
```

---

## Docker Setup

The project includes a Docker-based local development environment with:

* PHP 8.3 FPM
* Nginx
* MySQL 8.4
* Redis
* Mailpit
* Queue worker
* FastAPI AI service

### Start the containers

```bash
docker compose up -d --build
```

### Docker Services

| Service             | URL / Port              | Purpose                     |
| ------------------- | ----------------------- | --------------------------- |
| Laravel API / Nginx | `http://localhost:8000` | Main API entrypoint         |
| MySQL               | `localhost:3308`        | Database                    |
| Redis               | `localhost:6380`        | Cache and queue backend     |
| Queue Worker        | internal service        | Async Laravel queue worker  |
| Mailpit             | `http://localhost:8026` | Local email testing         |
| FastAPI AI Service  | `http://localhost:8001` | Quote analysis microservice |

FastAPI health check:

```bash
curl http://localhost:8001/health
```

FastAPI interactive docs:

```text
http://localhost:8001/docs
```

Run Laravel migrations and seeders inside Docker:

```bash
docker compose exec app php artisan migrate --seed
```

Run Laravel tests inside Docker:

```bash
docker compose exec app php artisan test
```

Stop the containers:

```bash
docker compose down
```

---

## Demo Credentials

If the demo seeder is enabled, the intended demo users are:

| Role                | Email                           | Password   |
| ------------------- | ------------------------------- | ---------- |
| Admin               | `admin@procurepilot.test`       | `password` |
| Requester           | `requester@procurepilot.test`   | `password` |
| Procurement Officer | `procurement@procurepilot.test` | `password` |
| Department Manager  | `manager@procurepilot.test`     | `password` |
| Finance Manager     | `finance@procurepilot.test`     | `password` |
| Viewer              | `viewer@procurepilot.test`      | `password` |

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

* Laravel API backend
* Sanctum authentication
* Multi-tenant organization scoping
* Roles and policies
* Departments API
* Vendors and vendor contacts API
* Purchase requests and items API
* Quotes and quote items API
* Local deterministic quote analysis
* FastAPI quote analysis microservice
* Laravel AI client integration with fallback behavior
* Quote comparison and recommendation scoring
* Approval workflow
* Invoices and VAT calculation
* Vendor scorecard API
* Activity logs API
* Attachments and file uploads
* Docker development setup
* GitHub Actions CI quality pipeline
* Laravel Pint code style checks
* Larastan/PHPStan static analysis with no errors
* OpenAPI documentation
* Postman collection
* Feature test coverage
* Production-readiness tests
* Health monitoring endpoint
* Reusable test scenario builders
* Redis queue job for submitted purchase requests
* Architecture documentation

Remaining / planned:

* Optional queue-based `QuoteAnalysisJob`
* OpenAI-compatible provider implementation
* Screenshots and demo script
* Final portfolio polish

---

## Roadmap

### Product Polish Milestone

* Add screenshots for the full procurement flow.
* Add a demo walkthrough script.
* Add final GitHub repository polish.

### AI Enhancement Milestone

* Add queued `QuoteAnalysisJob`.
* Add OpenAI-compatible provider interface.
* Add provider selection through environment configuration.
* Add more structured extraction fields for quote analysis.

### Portfolio Milestone

* Add additional screenshots and diagrams for portfolio presentation.
* Add screenshots of API results, test results, Docker containers, and CI.
* Add a concise demo script for recruiters and technical reviewers.

---

## Portfolio Context

This project is designed as a backend portfolio project for Laravel SaaS and AI-assisted business workflows.

It demonstrates:

* production-style Laravel backend architecture,
* multi-tenant SaaS data modeling,
* role-based authorization,
* service-layer business logic,
* API-first development,
* procurement workflow modeling,
* deterministic AI-assisted decision support,
* attachment upload workflows,
* FastAPI microservice integration,
* Dockerized development,
* automated CI,
* health monitoring and queue processing,
* test-driven confidence for business rules,
* auditability through activity logs.

The project is intentionally focused on backend depth rather than frontend UI. It is suitable for showcasing Laravel, SaaS architecture, clean code, API design, DevOps basics, and business workflow engineering.


Copyright © 2026 William Ammari. All rights reserved.
