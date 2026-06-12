# Proposal — foundations-modules-skeleton

## Why

Build Workplan Phase 1 ends with "clear conventions for how every module is built, tested, deployed" — the foundation every later phase inherits (`spec/05-release/Build_Workplan_v0.3-MVP.md` §2 Phase 1, Output/handoff). The modular-monolith ADR (`decisions/2026-06-11-modular-monolith-architecture.md`) decided nine bounded contexts with boundaries enforced as "conventions + tests", but nothing in the codebase realizes that decision yet. This change makes it executable: the nine-module skeleton with registered providers, the boundary law enforced by architecture tests from day zero, and the module-build template that all ~40 future module changes will follow. It is the first of the three F1 foundations changes (next: `foundations-domain-events-audit`, then `foundations-money-i18n-flags`).

## What Changes

- **Nine module roots under `app/Modules/`** — Catalog (0), Parties (K), Allocation (A), Procurement (D), Commerce (S), Inventory (B), Fulfilment (C), Finance (E), OperatorPanel (Admin) — each with a registered, initially-empty `{Name}ServiceProvider` (the module wiring seam).
- **A canonical module registry**: the `App\Modules\Module` enum (nine cases, spec-letter mapping per the CLAUDE.md terminology table) — the single source iterated by the architecture tests now and by the event envelope's `module` field in the next change.
- **The Filament operator panel moves home**: `App\Providers\Filament\AdminPanelProvider` → `App\Modules\OperatorPanel\Providers\AdminPanelProvider` (founder-confirmed 2026-06-12). Behavior unchanged: `/admin` path, panel id `admin`, login, seeded operator — the existing platform tests must pass unmodified.
- **A Pest architecture-test suite** (`tests/Architecture/`, new phpunit testsuite) enforcing the boundary law: module privacy (cross-module imports only via `Contracts\*` / `Events\*`), platform-never-imports-modules, registry ↔ filesystem conformance, and the forward-binding Eloquent convention (explicit, module-prefixed `$table`). Zero new dependencies: `pestphp/pest-plugin-arch` already ships with the installed Pest 4.7.2.
- **The module-build template** at `docs/module-template.md` (+ `docs/INDEX.md` row): canonical layout, public surface, provider / operator-surface / persistence / test conventions, naming cascade — the falsariga every F2+ module change follows.

## Capabilities

### New Capabilities

- `module-architecture`: the modular-monolith skeleton — the nine-module set and canonical registry, the boundary law and its test-suite enforcement, the operator-panel module placement, and the module-build template.

### Modified Capabilities

(none — the `platform` capability's requirements are untouched: the operator panel still authenticates at `/admin` exactly as specified; the panel-provider relocation is an implementation move, not a behavior change.)

## Impact

- **Code:** `app/Modules/**` (new: 9 module dirs + providers + `Module` enum), `bootstrap/providers.php` (9 providers added, 1 FQCN updated), `app/Providers/Filament/` (removed — panel provider relocated), `tests/Architecture/**` (new), `tests/Feature/Modules/**` + `tests/Unit/Modules/**` (new), `phpunit.xml` (one new testsuite entry), `tests/Pest.php` (binding for the new dir, if needed).
- **Docs:** `docs/module-template.md` (new), `docs/INDEX.md` (+1 row), `docs/development.md` (one cross-reference line).
- **Dependencies:** none added. `pestphp/pest-plugin-arch` is already in `composer.lock` (ships with Pest); `composer.json`/`composer.lock` must show zero churn at the end of this change.
- **Slice boundary — deliberately NOT in this change (declared future changes):**
  - The **event substrate** — `domain_events` / `audit_records` / `event_deliveries` tables, immutability triggers, delivery runner + sweep, hello-world exercising "DB + event bus + audit trail", and the **pgsql CI lane + migration-policy execution** (first change with migrations) → `foundations-domain-events-audit` (next F1 change).
  - **Money, i18n (6 locales), feature flags, `actor_role` helpers** → `foundations-money-i18n-flags` (third F1 change).
  - **Any domain entity, migration, route, job, or Filament resource inside modules** → F2+ module changes per the Build Workplan phases.
  - **Frontend platform shell** (TanStack SPA direction) → Module S storefront gate (open ADR).
  - **Identity/auth** beyond the existing bootstrap operator login → Module K gate (open ADR).
- **Traceability gaps (deliberate):** of Workplan Phase 1's signoff list, this change covers "module-build template + test patterns documented" and the repo-structure half of DEC-073's dev-team decisions. "Hello world … through CI/CD", observability, the audit substrate, and the "frontend platform shell … with authentication" land in the remaining F1 changes and their gated ADRs, as listed above.

