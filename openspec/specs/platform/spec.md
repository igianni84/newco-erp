# platform Specification

## Purpose
TBD - created by archiving change bootstrap-laravel-app. Update Purpose after archive.
## Requirements
### Requirement: Application Health

The platform SHALL expose an unauthenticated health endpoint at `GET /up` that returns HTTP 200 when the application boots and its database connection works.

_Source: Laravel platform baseline; prerequisite for hosting/monitoring (spec/02-prd/Architecture_v0.3-MVP.md, operational coherence)._

#### Scenario: Healthy application responds

- **WHEN** a client requests `GET /up`
- **THEN** the response status is `200`

### Requirement: Operator Panel Shell

The platform SHALL provide an operator panel at `/admin` (Filament) that requires authentication. Unauthenticated visitors SHALL be redirected to the panel login. A seeded operator account (credentials supplied via environment, never committed) SHALL be able to authenticate.

_Source: spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md — the Admin Panel is a first-class launch surface (manual-first operations, D24); this requirement establishes only its authenticated shell._

#### Scenario: Unauthenticated visitor is redirected to login

- **WHEN** an unauthenticated client requests `/admin`
- **THEN** the response redirects to the operator panel login page

#### Scenario: Seeded operator can authenticate

- **WHEN** the seeded operator submits valid credentials at the panel login
- **THEN** the operator reaches the panel dashboard

### Requirement: Quality Pipeline

The project SHALL provide the five Quality Commands defined in `CLAUDE.md` (format, test_filter, test, type_check, lint) runnable locally, and a CI workflow that runs on every push and pull request and fails when any of test, type_check, or lint fails. The CI workflow SHALL additionally run the full test suite against a PostgreSQL (≥ 17) service container — the `pgsql` lane, next to the in-memory SQLite lane — on every push and pull request, and fail when that lane fails: the parity guardrail of the "Postgres-truthful, SQLite-compatible" migration policy, landing in the same change that creates the repository's first domain migrations.

_Source: decisions/2026-06-11-dev-methodology-ralph-openspec.md — green CI is the loop's immune system ("broken code compounds across iterations") · decisions/2026-06-12-production-db-engine.md (Baseline — parity guardrail: "a `pgsql` CI lane (PostgreSQL service container, matrix next to SQLite, every push) lands in the same F1 change that creates the first domain migration") · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (pipeline)._

#### Scenario: CI fails on a failing test

- **WHEN** a commit containing a failing Pest test is pushed
- **THEN** the CI workflow run fails

#### Scenario: Local quality loop passes on a clean checkout

- **WHEN** the five Quality Commands run in order on a clean checkout
- **THEN** every configured command exits with status 0

#### Scenario: The suite runs against PostgreSQL in CI

- **WHEN** the CI workflow runs on a push or pull request
- **THEN** a `pgsql` lane executes `php artisan test` against a PostgreSQL (≥ 17) service container, and the workflow run fails if that lane fails

