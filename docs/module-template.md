# Module-Build Template

The *falsariga* every F2+ module change follows — the standing conventions for adding code to the nine-module monolith. **It documents decided conventions only; it invents nothing.** Every rule here traces to the modular-monolith ADR (`decisions/2026-06-11-modular-monolith-architecture.md`) and to decisions D1–D8 of the `foundations-modules-skeleton` change (`openspec/changes/archive/*-foundations-modules-skeleton/design.md` once archived). The boundary law is mechanically enforced by `tests/Architecture/` from day zero — this page tells you how to stay inside it, not how to negotiate with it.

For setup, quality commands, CI and the ralph loop, see [`development.md`](development.md). For what the system must do, see `spec/`; for how it behaves today, `openspec/specs/`.

## 1. The nine modules

One Laravel application; nine bounded contexts under `app/Modules/`. The single machine-readable source of "the nine" is the **`App\Modules\Module` enum** (`app/Modules/Module.php`, design D2): every architecture test **and** the composition root iterate `Module::cases()` — never a hardcoded list, so adding or removing a module is loud by construction.

| Module (namespace segment) | Spec letter | Bounded context |
|---|---|---|
| `Catalog` | 0 | PIM / Catalog — the Product Reference identity spine |
| `Parties` | K | Customers, clubs, memberships, Holds, Club Credit |
| `Allocation` | A | Allocations & sub-pools — no-oversell Layer 1 |
| `Procurement` | D | Purchase orders, supplier terms, consignment |
| `Commerce` | S | Sales / storefront, Vouchers, INV1 / INV2 / INV3 |
| `Inventory` | B | Stock, provenance, serialization — no-oversell Layer 2 |
| `Fulfilment` | C | Shipment & logistics (British spelling, per the spec) |
| `Finance` | E | Financial events, settlement, Xero sync |
| `OperatorPanel` | Admin | The operator surface — hosts the Filament panel |

Each enum case exposes the conventions mechanically: `->name` is the namespace segment (`namespace()` → `App\Modules\{CaseName}`), the backing `->value` is the snake_case table prefix (design D6), `->letter()` is the spec letter above, and `->providerClass()` is the standard service-provider FQCN. Derive from these — never restate them as literals in new code.

## 2. Canonical module layout

A module's full canonical layout (all PSR-4 under the existing `"App\\": "app/"` mapping — no `composer.json` change is ever needed to add a module):

```
app/Modules/{Name}/
├── Providers/
│   └── {Name}ServiceProvider.php   # the wiring seam (always present)
├── Contracts/                      # public surface — read contracts (interfaces)
├── Events/                         # public surface — domain events
├── Models/                         # Eloquent models (module-prefixed $table)
├── Services/                       # domain services / actions
├── Listeners/                      # event listeners (own + subscribed)
├── Http/                           # controllers, requests, resources
└── Filament/                       # operator surfaces (resources, pages, widgets)
```

**Lazy-creation rule (design D1):** at skeleton time only `Providers/` exists. Every other subdirectory appears **when first used** — no `.gitkeep` litter, no empty scaffolding. Creating a subdirectory like `Catalog/Models/` is free: PSR-4 resolves new paths live (no `composer dump-autoload` for new files — only for *moved/renamed* `App\**` classes, whose stale classmap entry must be regenerated).

Only the nine module directories and the `Module.php` registry file live directly under `app/Modules/` — `tests/Architecture/ModuleConformanceTest.php` enforces this by set-equality against `Module::cases()`.

## 3. Public surface & the boundary law

A module's **public surface** is exactly its `Contracts\*` and `Events\*` namespaces — the ADR's "domain events plus narrow read contracts" made mechanical. The normative law (design D3):

| From ↓ may depend on → | Own module | Other module `Contracts\*` / `Events\*` | Other module internals | Platform `App\*` (non-Modules) | Vendor |
|---|---|---|---|---|---|
| **Module code** | yes | yes | **NO** | yes | yes |
| **Platform code** (`App\*` outside `App\Modules`) | — | **NO** | **NO** | yes | yes |

Both `Contracts\*` and `Events\*` are part of the law **now**, before any contract or event class exists — the next changes create those classes inside an already-enforced rule. Modules talk to each other only through this surface (publish an event, depend on a read contract); never reach into another module's `Models`, `Services`, `Providers`, etc. The composition root `bootstrap/providers.php` is exempt (it references module provider FQCNs by design — it is configuration, not domain code). **String-based references** (Filament path discovery, config values, `DB::table('parties_holds')`) carry no symbol import and so are not flagged by the arch tests — the persistence prefix (§6) keeps such access *visible in review*; the template forbids reaching across modules by string.

**Amendment protocol (design D7 / spec "Boundary Enforcement"):** if a slice genuinely needs a new shared namespace (e.g. a platform-wide contract), the change that needs it **edits the architecture tests in the same change** and **justifies the amendment in that change's `design.md`**. Silently widening a red arch test to make it pass is a reviewable integrity violation — the law lives in the delta spec, and the tests iterate the enum so drift is loud.

Enforced by `tests/Architecture/ModuleBoundariesTest.php` (both directions: module-privacy and platform-never-imports-modules). The platform-namespace list is the one legitimate hardcoded array in the suite (`['App\Providers', 'App\Models', 'App\Http']` — the *complement* of the registry, which `Module::cases()` cannot derive); extend it there if a new platform root appears.

## 4. Service-provider conventions

Every module owns a standard `{Name}ServiceProvider extends Illuminate\Support\ServiceProvider` (`app/Modules/{Name}/Providers/`), registered in `bootstrap/providers.php` via the registry-driven spread `array_map(fn (Module $m) => $m->providerClass(), Module::cases())`. At skeleton time `register()` / `boot()` are empty — this is the **wiring seam** each F2+ change fills, with the framework-idiomatic wiring for that module:

- **routes** — `$this->loadRoutesFrom(...)` for module HTTP/API routes;
- **translations** — `$this->loadTranslationsFrom(...)` (all user-facing strings are localized — invariant 12; 6 locales);
- **event listeners** — register the module's listeners / subscribers (its own events and those it consumes from other modules' `Events\*`);
- **container bindings** — bind the module's `Contracts\*` interfaces to their concrete implementations (this is how *other* modules consume it);
- **config / views / migrations** — only if the module ships its own (migrations stay global, see §6).

Do not remove an "empty-looking" provider — it is the registered seam, and deleting one breaks `tests/Feature/Modules/ModuleProvidersTest.php` (registry conformance). The provider `extends Illuminate\Support\ServiceProvider`; OperatorPanel's Filament panel is a **separate** provider (`AdminPanelProvider extends PanelProvider`), not this one (design D5).

## 5. Operator-surface placement

The Filament operator panel lives in its owning module: `App\Modules\OperatorPanel\Providers\AdminPanelProvider` (panel id `admin`, path `/admin`, design D5). OperatorPanel obeys the **same boundary law** as the other eight — it owns no entities and gets no special access rights (Architecture § 2.3: it *operates* the modules' entities through the same backend contracts).

A module's own operator surfaces (Filament resources, pages, widgets) live in **that** module's `Filament\` namespace (`App\Modules\{X}\Filament\Resources\...`). Registering them with the panel is **one string-based line** added to `AdminPanelProvider` in that module's console task:

```php
->discoverResources(in: app_path('Modules/{X}/Filament/Resources'), for: 'App\\Modules\\{X}\\Filament\\Resources')
```

The arguments are **strings**, not symbol imports, so the arch tests do not flag the panel for "depending on" a module — deliberate (D5). Cross-module operator consoles (F6+) consume modules via `Contracts\*` / `Events\*` like everyone else, never by importing internals.

## 6. Persistence conventions

Domain tables are **module-prefixed**: `{module_snake}_` + snake_case plural entity (design D6, invariant 10 — table ownership readable in every query and migration). The prefix is the `Module` enum's backing value, mechanically. Examples:

| Module | Prefix | Example table |
|---|---|---|
| Catalog | `catalog_` | `catalog_product_masters` |
| Parties | `parties_` | `parties_holds` |
| Commerce | `commerce_` | `commerce_vouchers` |
| Inventory | `inventory_` | `inventory_stock_positions` |

Every Eloquent model under `App\Modules\**` **declares an explicit `$table`** with its module prefix (Eloquent cannot guess prefixed names — the explicitness is the point). FK columns keep their natural names (`product_reference_id`, no prefix). **Platform tables stay unprefixed** (`users`, and the event-substrate tables `domain_events` / `audit_records` / `event_deliveries` — platform, not module). Enforced forward-binding by `tests/Architecture/ModulePersistenceConventionsTest.php`, which fails the instant a module model omits or mis-prefixes its `$table`.

**Migrations** live in the standard global `database/migrations/` (Laravel-default ordering across modules; module ownership is carried by the table prefix, not the file location). **Migration policy — "Postgres-truthful, SQLite-compatible"** (`decisions/2026-06-12-production-db-engine.md`): write schema for production truth (CHECK constraints, partial indexes, expression indexes — all expressible in SQLite too); no PostgreSQL extensions at launch; if a PG feature ever lacks an SQLite equivalent, document the fallback in the migration and PG remains the truth. Tests run on SQLite `:memory:`; a `pgsql` CI lane lands with the first domain migration (the next foundations change).

## 7. Terminology & the naming cascade

Use **spec terms verbatim** in code identifiers (CLAUDE.md "Canonical Terminology"). The glossary of record is **`CONTEXT.md`** (root) — read it before naming anything, extend it when a term is resolved (definitions only, no implementation detail). Hard rules that must never be merged: the two distinct ownership fields (inventory `ownership_flag` vs purchase-order `ownership`, spec note N3); `Voucher` is never "coupon"/"credit"; `Club Credit` is distinct from `Voucher`.

The **naming cascade** (Wine → Product generalisation): the identity spine is the generic **Product Reference** (`Product Master` / `Product Variant` / `Product Reference`), not `Bottle Reference` / `Wine Master`; at launch `Product Type` has the single value `WINE`. **Module 0 § 18 is the source of truth** for the cascade (`spec/02-prd/Module_0_PRD_v0.3-MVP.md` § 18; `spec/CHANGES_v1.1_to_v0.3-MVP.md`). Wine-display aliases ("Bottle Reference / BR") are retained at the presentation layer; category-neutral module-internal names (Module S `Offer*`/`Voucher*`, Module E, the physical-unit names in Modules B/C) are unchanged.

## 8. Test patterns

- **Architecture suite, always-on.** `tests/Architecture/` is its own `<testsuite>` in `phpunit.xml`, so `php artisan test` runs it by default. Its three tests — `ModuleConformanceTest` (registry ↔ filesystem set-equality), `ModuleBoundariesTest` (the boundary law, both directions), `ModulePersistenceConventionsTest` (module-prefixed `$table`) — are **boot-free**: they locate the modules root by reflection on `Module::class`, not `app_path()`, and are not bound to `Tests\TestCase` in `tests/Pest.php`. They iterate `Module::cases()`, so a new module is covered automatically.
- **Per-module tests.** Module behaviour is tested under `tests/Feature/Modules/{X}/` (request/response, Filament render + authorization, jobs/listeners) and `tests/Unit/Modules/{X}/` (isolated services, models, value objects). Follow sibling conventions for naming and structure; use factories (create them if missing); cover the happy path **plus at least one failure/edge case**. Invariant-adjacent code (money, stock, FSM transitions, compliance gates) gets explicit edge-case tests — e.g. an oversell attempt MUST fail with the documented error.
- **SQLite `:memory:`** for the test database (`phpunit.xml`); money is always integer minor units + ISO 4217 code, never floats.
- **PHPStan level max, no baseline, no suppression** — narrow `mixed` and fix root types; the suite analyzes `tests/` too.
- **Red-proof discipline for arch rules.** A boundary/convention test that has never been seen red does not count as done: when you add or amend an arch rule, introduce a temporary violating fixture, observe the suite **FAIL**, remove it, and record both outputs (red, then green) in the change's `progress.md`. This is the only thing that catches a vacuously-green arch test.

## 9. Add-a-module-slice checklist

For every F2+ change that adds code to a module:

1. **Read first** — the module's `spec/02-prd/Module_{Letter}_PRD_v0.3-MVP.md` section(s) the slice cites, the `openspec/specs/` truth for the capability, `CONTEXT.md` for the terms, and any `knowledge/module-{letter}/rules.md`. Verify every class/table/route/event name exists before using it — never invent APIs.
2. **Lay out** — create only the subdirectories the slice uses (§2 lazy-creation); keep code inside `App\Modules\{Name}\`.
3. **Public surface** — define the `Contracts\*` you expose to other modules and the `Events\*` you emit; depend on *other* modules only through their `Contracts\*` / `Events\*` (§3). Need a new shared namespace? Follow the amendment protocol.
4. **Wire** — fill the module's `{Name}ServiceProvider` seam (routes, translations, listeners, bindings — §4). Operator surfaces go in the module's `Filament\` namespace and register via the string-based discover line in `AdminPanelProvider` (§5).
5. **Persist** — module-prefixed `$table` on every model; migration in `database/migrations/`, Postgres-truthful / SQLite-compatible (§6).
6. **Localize** — every user-facing string through Laravel localization (invariant 12); money as integer minor units + currency code (invariant 6).
7. **Test** — per-module Feature/Unit tests (happy path + edge case), invariant edge-cases explicit; the always-on arch suite covers boundaries for free (§8). Add a red-proof for any new arch rule.
8. **Quality loop green** — `format` → `test_filter` → `test` → `type_check` → `lint` (CLAUDE.md order), then `openspec validate <change> --strict`; zero unjustified `composer.json` / `composer.lock` churn.
9. **Persist memory** — `progress.md` (+ Codebase Patterns), `log.md`, `hot.md`; promote durable insights to `knowledge/` and architectural choices to `decisions/`.
