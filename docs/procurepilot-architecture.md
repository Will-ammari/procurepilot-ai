# ProcurePilot AI Architecture

ProcurePilot AI is a production-style Laravel SaaS backend for procurement workflows. It is designed around organization-level tenant isolation, role-based authorization, approval workflows, quote comparison, invoicing, auditability, and asynchronous operational jobs.

## High-level architecture

```mermaid
flowchart LR
    Client[API Client / Postman / Frontend] --> Nginx[Nginx]
    Nginx --> Laravel[Laravel API]
    Laravel --> MySQL[(MySQL)]
    Laravel --> Redis[(Redis)]
    Laravel --> Queue[Queue Worker]
    Laravel --> Mailpit[Mailpit]
    Laravel --> FastAPI[FastAPI Quote Analysis Service]
```

## Request lifecycle

```mermaid
sequenceDiagram
    participant User
    participant API as Laravel API
    participant Policy as Policy Layer
    participant Service as Service Layer
    participant DB as MySQL
    participant Queue as Redis Queue

    User->>API: Submit purchase request
    API->>Policy: authorize submit
    Policy-->>API: allowed
    API->>Service: submit request
    Service->>DB: transaction update status
    Service->>Queue: dispatch afterCommit job
    Service-->>API: fresh resource
    API-->>User: 200 OK
```

## Domain model

```mermaid
erDiagram
    ORGANIZATIONS ||--o{ USERS : has
    ORGANIZATIONS ||--o{ DEPARTMENTS : has
    ORGANIZATIONS ||--o{ VENDORS : owns
    ORGANIZATIONS ||--o{ PURCHASE_REQUESTS : owns
    DEPARTMENTS ||--o{ PURCHASE_REQUESTS : receives
    USERS ||--o{ PURCHASE_REQUESTS : requests
    PURCHASE_REQUESTS ||--o{ PURCHASE_REQUEST_ITEMS : contains
    PURCHASE_REQUESTS ||--o{ QUOTES : receives
    VENDORS ||--o{ QUOTES : submits
    QUOTES ||--o{ QUOTE_ITEMS : contains
    QUOTES ||--o| QUOTE_ANALYSES : analyzed_by
    PURCHASE_REQUESTS ||--o{ APPROVAL_STEPS : requires
    PURCHASE_REQUESTS ||--o{ INVOICES : billed_by
    VENDORS ||--o{ INVOICES : issues
    VENDORS ||--o| VENDOR_SCORECARDS : measured_by
    ORGANIZATIONS ||--o{ ACTIVITY_LOGS : audits
```

## Design decisions

- Controllers stay thin and delegate business logic to service classes.
- Form Requests own validation and organization-scoped existence checks.
- Policies enforce authorization and prevent cross-tenant access.
- Services enforce business invariants such as invoice eligibility, approval sequencing, and tenant-safe lookup.
- Redis queue workers process asynchronous operational jobs.
- Activity logs provide auditability for important workflow decisions.
- Docker Compose runs the API, MySQL, Redis, Nginx, Mailpit, queue worker, and FastAPI sidecar locally.

## Production-readiness checklist

- Organization-scoped data model.
- Policy-based authorization.
- Feature tests for tenant isolation, authorization, billing, and health checks.
- CI pipeline for automated test execution.
- Health endpoint for dependency checks.
- Queue worker for asynchronous processing.
