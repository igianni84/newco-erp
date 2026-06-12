# Design — foundations-modules-skeleton

## Context

Repo state at authoring (2026-06-12): `bootstrap-laravel-app` archived — Laravel 13.15.0 at the root, Filament v5.6.7 panel `admin` at `/admin` (`app/Providers/Filament/AdminPanelProvider.php`), Pest 4.7.2 suite **36 tests / 99 assertions green**, PHPStan **level max, 0 errors, no baseline**, CI green. The only living capability spec is `openspec/specs/platform/spec.md`.

Both F1 ADR gates are closed: production DB = PostgreSQL ≥ 17 (`decisions/2026-06-12-production-db-engine.md`) and event substrate = transactional outbox (`decisions/2026-06-12-event-substrate-and-audit-store.md`). This change implements the *structural* half of the monolith ADR (`decisions/2026-06-11-modular-monolith-architecture.md`); the substrate ADR is implemented by the NEXT change (`foundations-domain-events-audit`) and must NOT be anticipated here — no event tables, no platform event namespace, no migrations of any kind.

The spec is deliberately tech-agnostic about repo structure, module internal layout and enforcement tooling (DEC-073; Architecture § 0.3 "NOT tech-implementation", § 0.5 "wire-level enforcement is dev-team scope"). Those decisions are made here, in design, and frozen into the module-build template.

## Goals / Non-Goals

**Goals:**
- The nine module roots exist with registered providers; the app boots identically to today.
- The boundary law is mechanically enforced by tests from day zero — *before* any module code exists that could violate it.
- The module-build template documents every convention an F2+ module change needs (Workplan Phase 1 signoff: "module-build template + test patterns documented").
- Zero new dependencies; `composer.json`/`composer.lock` untouched.

**Non-Goals:**
- The event substrate, its tables/triggers/runner, the hello-world, the pgsql CI lane, the migration policy execution (→ `foundations-domain-events-audit`).
- Money, i18n, feature flags, `actor_role` helpers (→ `foundations-money-i18n-flags`).
- Any domain entity, migration, route, job, listener, contract or Filament resource inside a module (→ F2+ per Build Workplan).
- The frontend platform shell, customer/producer auth (gated ADRs).
- Reopening any decided ADR.

## Decisions

### D1 — Namespace shape and lazy directories

`App\Modules\{Name}\` with PSR-4 already satisfied by the existing `"App\\": "app/"` mapping (no composer change). Provider at `App\Modules\{Name}\Providers\{Name}ServiceProvider`, mirroring Laravel's `app/Providers` idiom inside each module. Module names are the CLAUDE.md canonical ones — `Catalog, Parties, Allocation, Procurement, Commerce, Inventory, Fulfilment, Finance, OperatorPanel` (note: **Fulfilment**, British spelling, per the spec). **Only `Providers/` is created now**; all other subdirectories (`Contracts/`, `Events/`, `Models/`, `Services/`, `Listeners/`, `Http/`, `Filament/`) appear when first used — no `.gitkeep` litter. The template documents the canonical full layout.

### D2 — Canonical module registry: `App\Modules\Module` enum

A string-backed enum at `app/Modules/Module.php` is the single machine-readable source for "the nine modules": cases named like the namespaces (`Catalog` … `OperatorPanel`), values = snake_case names (`'catalog'` … `'operator_panel'`), plus `letter(): string` (spec letters `0, K, A, D, S, B, C, E, Admin` per the CLAUDE.md table) and `namespace(): string` (`'App\Modules\' . $this->name`). Architecture tests iterate `Module::cases()` — never hardcoded lists. The next change's event envelope (`module` column) gets a typed anchor for free; what string it *persists* stays that change's decision. The enum (and nothing else besides the nine directories) lives directly under `app/Modules/` — conformance-tested.

### D3 — The boundary law (normative)

| From ↓ may depend on → | Own module | Other module `Contracts\*`/`Events\*` | Other module internals | Platform `App\*` (non-Modules) | Vendor |
|---|---|---|---|---|---|
| **Module code** | yes | yes | **NO** | yes | yes |
| **Platform code** (`App\*` outside `App\Modules`) | — | **NO** | **NO** | yes | yes |

`Contracts\*` + `Events\*` are a module's *public surface* — exactly the ADR's "domain events plus narrow read contracts" made mechanical. Both namespaces are part of the law NOW even though no contract/event classes exist yet: the next changes create them inside an already-enforced rule. The composition root `bootstrap/providers.php` (outside `app/`, configuration not domain code) is exempt: it references module provider FQCNs by design. String-based references (e.g. Filament path discovery, config values) are not symbol dependencies and are not flagged — by design, see D5.

### D4 — Enforcement mechanism: Pest architecture tests

The monolith ADR mandates "conventions + tests". Chosen: **pest-plugin-arch**, which already ships with the installed Pest 4.7.2 (verified in `composer.lock`) — zero new dependencies, hence no new-dependency ADR needed. Alternatives rejected: **Deptrac** (new heavyweight dev dependency → would require an ADR; separate config dialect outside the test suite) and **custom PHPStan rules** (higher build/maintenance cost; PHPStan remains available as a *later* additional layer, per the ADR's "enforceable by static analysis later"). Implementation note for the loop: **verify the exact expectation API against `vendor/pestphp/pest-plugin-arch` before writing** (e.g. `expect('App\Modules\Catalog')->not->toUse([...])->ignoring([...])`) — never write arch expectations from memory.

### D5 — OperatorPanel hosts the Filament panel; uniform boundary rules (founder-confirmed 2026-06-12)

`AdminPanelProvider` relocates: `app/Providers/Filament/AdminPanelProvider.php` → `app/Modules/OperatorPanel/Providers/AdminPanelProvider.php` (namespace updated, `bootstrap/providers.php` updated, empty `app/Providers/Filament/` removed). The class body is otherwise unchanged — panel id `admin`, path `admin`, `->login()`, default Dashboard. OperatorPanel also gets the standard `OperatorPanelServiceProvider` like every other module (the panel provider is Filament-specific; the module provider is the standard wiring seam — both registered).

OperatorPanel gets **no special access rights** (Architecture § 2.3: the Admin Panel "owns no entities — it operates the modules' entities" through the same backend contracts). The operator-surface composition pattern for F2+: each module's Filament resources live in the owning module (`App\Modules\{X}\Filament\Resources\...`); registering them = one **string-based** `->discoverResources(in: app_path('Modules/{X}/Filament/Resources'), for: 'App\Modules\{X}\Filament\Resources')` line added to `AdminPanelProvider` in that module's console task. Strings are not symbol imports, so arch tests don't flag discovery — deliberate. Cross-module consoles (F6+) consume modules via `Contracts\*`/`Events\*` like everyone else.

### D6 — Persistence conventions (founder-confirmed 2026-06-12)

Domain tables are **module-prefixed**: `{module_snake}_` + snake_case plural entity (e.g. `catalog_product_masters`, `parties_holds`, `commerce_vouchers`, `inventory_stock_positions`). Every Eloquent model under `App\Modules\**` declares an explicit `$table` with its module's prefix (Eloquent cannot guess prefixed names — the explicitness is the point: table ownership readable in every query and migration, invariant 10 made visible). FK columns keep natural names (`product_reference_id`, no prefix). **Platform tables stay unprefixed** (`users`, and the substrate ADR's `domain_events`, `audit_records`, `event_deliveries` — platform, not module). Migrations live in the standard global `database/migrations/` (Laravel-default ordering across modules; module ownership is carried by the table prefix, not the file location). The prefix for each module = the `Module` enum's backing value, mechanically. Rejected alternative: bare spec-named tables (`product_masters`) — pure-Eloquent zero-config, but table ownership becomes invisible at the SQL layer and collisions get handled ad hoc; the founder chose visibility.

### D7 — The module-build template (`docs/module-template.md`)

The Workplan Phase 1 deliverable, written once the conventions above are real. Required sections (tasks reference these numbers): (1) the nine modules + spec letters table; (2) canonical module layout tree with the lazy-creation rule; (3) public surface & boundary law incl. the amendment protocol (a change that needs a new shared namespace edits the arch tests in the same change and justifies it in its design.md); (4) service-provider conventions (what wiring belongs there when it arrives: routes, translations, listeners, bindings); (5) operator-surface placement (D5 pattern); (6) persistence conventions (D6, plus the Postgres-truthful/SQLite-compatible migration policy by reference to the DB ADR); (7) terminology & naming cascade (spec terms verbatim in identifiers; `CONTEXT.md` glossary of record; Module 0 § 18 naming-cascade source-of-truth per Workplan Phase 1 carry (ii)); (8) test patterns (Architecture suite always-on; per-module tests in `tests/Feature/Modules/{X}` and `tests/Unit/Modules/{X}`; SQLite `:memory:`; PHPStan level max type-clean, no suppression; red-proof discipline when adding arch rules); (9) the add-a-module-slice checklist (the falsariga for every F2+ change). The template *documents decided conventions* — it must not invent new ones.

### D8 — Architecture suite placement

New top-level `tests/Architecture/` directory + an `Architecture` testsuite entry in `phpunit.xml` (so `php artisan test` runs it by default — Workplan Phase 1: the suite "demonstrates the standard patterns"). Arch expectations and the conformance/convention tests need no Laravel boot; check whether `tests/Pest.php` needs a binding for the new directory (it currently binds `Tests\TestCase` for `Feature`). `php artisan test --filter` must keep working (CLAUDE.md `test_filter`).

## Risks / Trade-offs

- **[Vacuously green arch tests]** With no module code yet, boundary tests could pass forever without ever being able to fail. → Every arch-test task carries a mandatory **red-proof**: introduce a temporary violating fixture, observe the suite FAIL, remove it, record both outputs in `progress.md`. A boundary test that has never been seen red does not count as done.
- **[pest-plugin-arch API drift]** Arch expectation names/signatures written from memory may not exist in the installed version. → Verify against `vendor/pestphp/pest-plugin-arch/src/` before writing (lessons.md verify-before-write discipline applies to vendor APIs too).
- **[Panel relocation breakage]** Namespace assumptions (config caches, route names, asset publishing) could surface after the move. → The existing `OperatorPanelTest` / `OperatorSeederTest` are the regression net and MUST pass **unmodified**; run them before and after the move in the same iteration. The published Filament assets and `config/` are untouched by design.
- **[String-based DB access escapes import analysis]** `DB::table('parties_holds')` from another module has no class import to flag. → Accepted residual at this stage: the D6 prefix makes such access *visible in review*, the template forbids it, and a query-level convention test can be added when the first real temptation appears (post-F2 evidence, not speculation).
- **[Law amendments under loop pressure]** A future loop iteration could "fix" a red arch test by widening the law instead of fixing the violation. → The law lives in this change's delta spec (truth after archive); RALPH integrity rules + the template's amendment protocol (same-change edit + design justification) make silent widening a reviewable event. Arch tests iterate the enum, so adding a tenth module is loud by construction.
- **[Empty providers feel like dead code]** Nine empty providers add boot overhead ≈ zero but invite "cleanup". → The template states their role: the module wiring seam every F2+ change fills (routes, listeners, bindings). Removing one breaks the registry conformance test.

## Migration Plan

Pure additive code + one class relocation; no data, no schema, no config semantics change. Deploy = merge; rollback = `git revert` of the branch merge. The only behavior-adjacent step is the panel-provider move (D5), guarded by the existing platform tests.

## Open Questions

None. The two points the spec leaves open for this slice (panel location, table naming) were resolved in the founder interview of 2026-06-12 and are recorded as D5 and D6.


