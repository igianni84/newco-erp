# module-architecture Specification

## Purpose
TBD - created by archiving change foundations-modules-skeleton. Update Purpose after archive.
## Requirements
### Requirement: Module Skeleton and Registry

The application SHALL define exactly nine module namespaces under `App\Modules\`: `Catalog` (spec module 0), `Parties` (K), `Allocation` (A), `Procurement` (D), `Commerce` (S), `Inventory` (B), `Fulfilment` (C), `Finance` (E), `OperatorPanel` (Admin). Each module SHALL have a service provider `App\Modules\{Name}\Providers\{Name}ServiceProvider` registered in `bootstrap/providers.php`. A canonical registry (`App\Modules\Module` enum) SHALL enumerate the nine modules with their spec-letter mapping and SHALL be the single source iterated by architecture tests.

_Source: decisions/2026-06-11-modular-monolith-architecture.md (nine bounded contexts under `app/Modules/`, module ↔ spec letter mapping) · spec/02-prd/Architecture_v0.3-MVP.md § 2.1 (the launch surfaces — eight modules + the Admin Panel) · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (repo structure is a dev-team Phase-1 decision, DEC-073 — resolved here)._

#### Scenario: Nine module providers load

- **WHEN** the application boots
- **THEN** the service providers of all nine modules (Catalog, Parties, Allocation, Procurement, Commerce, Inventory, Fulfilment, Finance, OperatorPanel) are loaded

#### Scenario: Canonical registry matches the spec mapping

- **WHEN** the `Module` registry is enumerated
- **THEN** it yields exactly nine modules with spec letters 0, K, A, D, S, B, C, E, Admin per the CLAUDE.md module ↔ letter table

#### Scenario: No stray entries at the modules root

- **WHEN** `app/Modules/` is inspected
- **THEN** it contains exactly the nine module directories and the registry file, nothing else

### Requirement: Module Boundary Law

Module code SHALL NOT depend on another module's internals. The only cross-module symbols a module MAY reference are the target module's `Contracts\*` and `Events\*` namespaces (its public surface). Modules MAY depend on platform code (`App\*` outside `App\Modules`) and on vendor packages. Platform code SHALL NOT depend on any `App\Modules\*` symbol; the composition root (`bootstrap/providers.php`) is exempt as configuration.

_Source: decisions/2026-06-11-modular-monolith-architecture.md (modules communicate exclusively through domain events plus narrow read contracts; "no cross-module imports" as the mechanical rule) · spec/02-prd/Architecture_v0.3-MVP.md § 1.2 (domain events as cross-module contracts) · § 2.2 (cross-module readers consume via events and reads, never by editing) · § 0.5 (enforcement mechanism is dev-team scope, DEC-073 — resolved here) · CLAUDE.md invariant 10._

#### Scenario: Cross-module internal import fails the suite

- **WHEN** code in one module references another module's symbol outside that module's `Contracts\*` / `Events\*` namespaces
- **THEN** the architecture test suite fails

#### Scenario: Public-surface import is allowed

- **WHEN** code in one module references another module's `Contracts\*` or `Events\*` symbols
- **THEN** the architecture test suite does not flag it

#### Scenario: Platform never imports modules

- **WHEN** platform code (`App\*` namespaces outside `App\Modules`) references any `App\Modules\*` symbol
- **THEN** the architecture test suite fails

### Requirement: Module Persistence Conventions

Module persistence SHALL be module-private: exactly one module owns each table's rows, lifecycle and operations. Every domain table introduced by a module SHALL be named with the owning module's snake_case prefix (e.g. `catalog_product_masters`, `parties_holds`), and every **domain** Eloquent model under `App\Modules\**` SHALL declare an explicit `$table` carrying that prefix. Platform tables — owned by no module (e.g. the event-substrate tables), **or platform-foundation records that mirror the framework's flat naming** — remain unprefixed. Specifically, an **authentication principal** (a model implementing `Illuminate\Contracts\Auth\Authenticatable`, e.g. the OperatorPanel `Operator` mapped to the flat `operators` table) is **exempt** from the module-prefix convention even though it lives inside a module: it is a platform-foundation login shell reached only through its named guard, never by a cross-module Eloquent query, join or import — so invariant 10's *substance* (no cross-module DB access) is preserved by the boundary-import test and the guard-by-name access pattern, not by the cosmetic table prefix. The convention SHALL be enforced by an architecture test that binds from the first module **domain** model onward and that skips authentication principals.

_Source: CLAUDE.md invariant 10 (no cross-module DB access) · spec/02-prd/Architecture_v0.3-MVP.md § 2.2 (for every entity exactly one module owns the row, lifecycle, and operations) · table-naming choice is dev-team scope (DEC-073), founder-confirmed 2026-06-12 — recorded in design.md Decision D6 · the auth-principal carve-out is design.md Decision D7 + decisions/2026-06-15-auth-principal-table-naming.md (auth principals are platform-foundation login shells; mirrors the framework's flat `users`/`operators` naming; forward-binds the deferred customer/producer principals) · decisions/2026-06-15-identity-auth.md (auth is a platform foundation; the Operator principal is owned by the OperatorPanel module)._

#### Scenario: Module model declares its prefixed table

- **WHEN** a class under `App\Modules\**` extending the Eloquent `Model` is introduced with no explicit `$table`, or with a `$table` not starting with the owning module's prefix
- **THEN** the architecture test suite fails

#### Scenario: Empty model set is handled honestly

- **WHEN** no module models exist yet
- **THEN** the persistence-convention test passes by a proven-empty scan, not by error or skip

#### Scenario: Authentication principal is exempt from the prefix

- **WHEN** a concrete Eloquent model under `App\Modules\**` implements `Illuminate\Contracts\Auth\Authenticatable` and maps to an unprefixed auth table (e.g. the OperatorPanel `Operator` mapped to `operators`)
- **THEN** the persistence-convention test passes — the auth principal is recognised as a platform-foundation login shell and skipped — while every non-principal module model still requires its module prefix

### Requirement: Operator Panel Module Placement

The OperatorPanel module SHALL host the Filament operator panel: the panel provider lives at `App\Modules\OperatorPanel\Providers\AdminPanelProvider`. The OperatorPanel module SHALL obey the same boundary law as the other eight modules — no special access rights to other modules' internals. Module-owned operator surfaces (Filament resources, F2+) live in the owning module's `Filament\` namespace and are registered with the panel via string-based path discovery, never via class imports from OperatorPanel into module internals.

_Source: spec/02-prd/Architecture_v0.3-MVP.md § 2.1 + § 2.3 (the Admin Panel is a first-class cross-cutting surface; it owns no entities — it operates the modules' entities; consoles compose from events the modules record) · decisions/2026-06-11-modular-monolith-architecture.md (OperatorPanel is one of the nine bounded contexts) · relocation founder-confirmed 2026-06-12 — recorded in design.md Decision D5._

#### Scenario: Panel served from the OperatorPanel module

- **WHEN** the application boots
- **THEN** the Filament panel `admin` is provided by `App\Modules\OperatorPanel\Providers\AdminPanelProvider` and the legacy class `App\Providers\Filament\AdminPanelProvider` no longer exists

#### Scenario: Panel behavior is unchanged by the move

- **WHEN** an unauthenticated client requests `/admin`
- **THEN** it is redirected to the panel login, and all pre-existing platform-capability tests pass unmodified

#### Scenario: OperatorPanel has no special rights

- **WHEN** OperatorPanel code references another module's symbols outside `Contracts\*` / `Events\*`
- **THEN** the architecture test suite fails, exactly as for any other module

### Requirement: Boundary Enforcement in the Default Test Suite

The module boundary rules SHALL be enforced by automated architecture tests that execute as part of the default test command (`php artisan test`) and fail the suite on any violation. The architecture tests SHALL iterate the canonical `Module` registry (no hardcoded module lists) so that the law mechanically covers any future module set drift. Amending the boundary law (e.g. introducing a new shared platform namespace) SHALL happen in the same change that needs it, with the arch-test edit justified in that change's design document.

_Source: decisions/2026-06-11-modular-monolith-architecture.md (Reasoning 3–4: boundaries as conventions + tests; explicit mechanical rules for autonomous loops) · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (Tests: "its test suite demonstrates the standard patterns"; Signoff: "module-build template + test patterns documented")._

#### Scenario: Architecture suite runs by default

- **WHEN** `php artisan test` executes
- **THEN** the Architecture test suite runs as part of the default run

#### Scenario: Violations are demonstrably caught

- **WHEN** a deliberate boundary violation is introduced (temporary fixture during implementation)
- **THEN** the architecture suite fails, and the demonstration (red output, then green after removal) is recorded in the change's progress log

### Requirement: Module-Build Template

A module-build template SHALL exist at `docs/module-template.md` documenting, at minimum: the nine modules with their spec letters; the canonical module layout and namespaces; the public surface and boundary law (including how to amend it); service-provider conventions; operator-surface placement; persistence conventions (module-prefixed tables, explicit `$table`, migrations location); terminology and the naming cascade (spec terms verbatim, `CONTEXT.md` as glossary of record, Module 0 § 18 as naming source-of-truth); and the test patterns every module change follows. `docs/INDEX.md` SHALL reference it.

_Source: spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (Subtasks: "write the module-build template + integration-test pattern"; Signoff: "module-build template + test patterns documented"; MVP carry (ii): the naming cascade is the canonical naming the module-build template adopts; Output/handoff: "clear conventions for how every module is built, tested, deployed")._

#### Scenario: Template exists, is complete and indexed

- **WHEN** a contributor (human or loop) opens `docs/module-template.md`
- **THEN** it documents layout, public surface and boundary law, provider conventions, operator-surface placement, persistence conventions, terminology/naming cascade, and test patterns, and `docs/INDEX.md` carries a row for it

#### Scenario: Template is registry-complete

- **WHEN** the template's module table is compared with the `Module` registry
- **THEN** every module appears with its spec letter

