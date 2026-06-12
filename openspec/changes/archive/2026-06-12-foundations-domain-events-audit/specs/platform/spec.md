## MODIFIED Requirements

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
