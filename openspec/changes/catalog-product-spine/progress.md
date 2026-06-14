# Progress — catalog-product-spine

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **House enum style (`App\Platform\*`, `App\Modules\Module`):** PascalCase case
  name, lowercase/snake_case backing value, rich docblock ending with the legend
  `- case name = … / - backing value = …`. Pure-vocabulary enums (`ActorRole`,
  `DeliveryMode`) are bare case lists — **no speculative helper methods** (YAGNI;
  add accessors only when a call-site needs them). Richer enums (`SupportedLocale`,
  `Currency`) add `fallback()`/`base()`/`assertSupported()` only because real
  call-sites use them. Catalog `ProductType`/`LifecycleState` follow the bare form.
- **Enum test convention (`tests/Unit/.../EnumsTest.php`):** pure unit test, **no
  `uses(RefreshDatabase)`**. Per enum: loop `Enum::cases()` into a `name => value`
  map and assert verbatim & order-sensitive with `->toBe([...])`; add an explicit
  `->toHaveCount(n)` length guard when "exactly N cases" is itself a spec rule;
  add a fail-closed `expect(fn () => Enum::from('bogus'))->toThrow(ValueError::class)`
  edge case. Mirrors `tests/Unit/Platform/EnumsTest.php`.
- **Spec-driven enum guards:** `ProductType::cases()` length-1 is the AC-0-XM-9
  "WINE is the only launch type" guard; `LifecycleState` carries the full
  four-state domain (PRD §4.1) though this slice transitions none of it — the
  enum exists so `catalog-lifecycle-approval` drives it without a migration.
- **PSR-4:** `App\Modules\Catalog\Enums\X` → `app/Modules/Catalog/Enums/X.php`
  (`App\` → `app/`); `Write` creates parent dirs, no autoload edit needed.
- **Spine DB-entity creation template (task 2.1 Format → repeated by 2.2–4.2).**
  Each entity = `catalog_*` migration + `Models\X` + `Database\Factories\Catalog\XFactory`
  + `Events\XCreated` + `Actions\CreateX`.
  - **Migration:** `$table->id()`; entity cols; `lifecycle_state` string
    `->default(LifecycleState::Draft->value)`; `version` `unsignedInteger()->default(1)`
    (§4.8 floor); `timestampsTz()` (audit). After `Schema::create`, a **driver-guarded**
    `lifecycle_state` CHECK (`if (DB::getDriverName()==='pgsql')`, values from
    `LifecycleState::cases()`, constraint `catalog_X_lifecycle_state_check`) — mirrors the
    `domain_events.actor_role` CHECK verbatim.
  - **Model:** `$table='catalog_X'`; `$guarded=[]` (the action is the sole writer);
    `casts(): ['lifecycle_state'=>LifecycleState::class,'version'=>'integer']`; full
    `@property` block. **Wire the off-convention factory with a typed `newFactory(): XFactory`
    override** — `protected static $factory` ALONE leaves Larastan inferring `mixed` on
    `X::factory()->create()` (factory is under `Database\Factories\Catalog\`, off the
    `App\Models` name convention); the explicit return type fixes static analysis.
  - **Factory:** `protected $model = X::class` (authoritative — `Factory::modelName()` returns
    it directly); coherent fixture tuples; born `LifecycleState::Draft`.
  - **Event class:** `const NAME` (verbatim §14.1) + `const ENTITY_TYPE` + static
    `payload(X): array` (PII-free; ids + non-PII business data). Repo has NO typed class
    constants — keep them untyped. The event NAMES no caller (dependency runs action→event;
    prose-reference the action, never `{@see}` it — Pint's `fully_qualified_strict_types` would
    import it and create a use-cycle).
  - **Action:** `class CreateX` with readonly-promoted `DomainEventRecorder` + `ActorContext`
    ctor deps (container-resolved via `app(CreateX::class)`); one `handle(...)`; ONE
    `DB::transaction` wrapping `X::create([...,'lifecycle_state'=>LifecycleState::Draft])` then
    `recorder->record(name: XCreated::NAME, module: Module::Catalog->value,
    actorRole: $actor->role(), actorId: $actor->actorId(), entityType: XCreated::ENTITY_TYPE,
    entityId: (string)$x->id, payload: XCreated::payload($x))`. The recorder's
    `NotInTransactionException` guard makes write+emit atomic.
  - **Test:** `tests/Feature/Modules/Catalog/XTest.php`, `uses(RefreshDatabase::class)` — the
    action opens its OWN tx, so the recorder's level-0 guard is satisfied by the savepoint under
    the wrapper (no level-0 path to test here; that is the recorder's own test). Fetch the event
    with `->sole()` (non-null for PHPStan AND asserts exactly-one in one call; `get()->first()`
    is nullable → PHPStan `property.nonObject`). Assert payload BY KEY (trap 3). Scope-guard
    assertion: `where('name','like','%Activated%')->count()===0` (+ Retired). A factory test
    documents the factory as a pure fixture (bypasses the action → records NO event).

---

## [2026-06-14 19:23] — 1.1 Catalog enums (ProductType + LifecycleState)
- **What:** Added the two Catalog spine enums (design D2/D3) — `ProductType:string`
  (sole case `Wine='wine'`) and `LifecycleState:string` (`Draft/Reviewed/Active/
  Retired` → `draft/reviewed/active/retired`). Pure case-list enums with house-style
  docblocks citing Module 0 PRD §3.1/§16 + AC-0-XM-9 (ProductType) and §4.1/§14.2
  (LifecycleState). No DB this task.
- **Files changed:**
  - `app/Modules/Catalog/Enums/ProductType.php` (new)
  - `app/Modules/Catalog/Enums/LifecycleState.php` (new)
  - `tests/Unit/Modules/Catalog/Enums/EnumsTest.php` (new — 4 tests, 6 assertions)
  - `openspec/changes/catalog-product-spine/tasks.md` (1.1 checked)
  - (folded in the still-uncommitted authoring artifacts onto the branch: the ADR
    `decisions/2026-06-14-catalog-category-neutral-representation.md` + its
    `decisions/INDEX.md` row — every task's standing rule says to read the ADR, so
    it belongs on the branch with the first implementation commit.)
- **Quality loop:** green — pint clean · filtered test 4/4 · full suite **258/258**
  (910 assertions, was 254 pre-task) · phpstan **0 @ max** · pint --test clean ·
  `openspec validate catalog-product-spine --strict` valid · `git diff main --
  composer.{json,lock}` empty.
- **Learnings for future iterations:**
  - The bare-enum decision (no helpers) keeps PHPStan-max clean with zero effort and
    leaves the lifecycle change free to add transition logic where it belongs.
  - `->toHaveCount(1)` reads as the explicit WINE-only-at-launch contract even though
    the `toBe` map already implies it — keep it; it documents AC-0-XM-9 intent.
  - Next task **2.1 (Format)** is the **first DB-touching task** → the five
    SQLite↔PG17 portability traps engage (driver-guarded enum CHECK mirroring
    `domain_events`, assert json/TranslatableText by key, named test doubles) and the
    PG17 cross-engine run becomes mandatory before "done". It also establishes the
    `Events/` one-class-per-event convention + the `Create*` action + transactional
    `*Created` recorder pattern that 2.2–4.2 all repeat.
---

## [2026-06-14 19:39] — 2.1 Format (catalog_formats + model + factory + event + action)
- **What:** First DB-touching spine slice. The `catalog_formats` migration (id, name,
  size_label, volume_ml, `lifecycle_state` + driver-guarded PG CHECK, `version`, `timestampsTz`)
  + `Format` model + `FormatFactory` + `FormatCreated` event (const NAME/ENTITY_TYPE + static
  PII-free `payload()`) + `CreateFormat` action (one `DB::transaction`: insert `draft` + record
  `FormatCreated` via `DomainEventRecorder`, actor from `ActorContext`). Establishes the
  `Events/`-class + `Create*`-action + transactional-recorder conventions for 2.2–4.2 (now in
  Codebase Patterns).
- **Files changed:**
  - `database/migrations/2026_06_14_000001_create_catalog_formats_table.php` (new)
  - `app/Modules/Catalog/Models/Format.php` (new)
  - `app/Modules/Catalog/Events/FormatCreated.php` (new)
  - `app/Modules/Catalog/Actions/CreateFormat.php` (new)
  - `database/factories/Catalog/FormatFactory.php` (new)
  - `tests/Feature/Modules/Catalog/FormatTest.php` (new — 4 tests)
  - `openspec/changes/catalog-product-spine/tasks.md` (2.1 checked)
- **Quality loop:** green — pint clean · FormatTest 4/4 · full suite **262/262** (930 assertions,
  +4 vs the 258 baseline) · phpstan **0 @ max** · pint --test clean · `openspec validate
  catalog-product-spine --strict` valid · `git diff main -- composer.{json,lock}` empty.
  **PG17 cross-engine verified: 262/262 on `postgres:17`**, with a driver guard printing `pgsql`
  first (proves the run hit real PG, not a silent SQLite fallback).
- **Learnings for future iterations:**
  - Off-convention factory (`Database\Factories\Catalog\`) needs the typed
    `newFactory(): XFactory` override — `protected static $factory` alone left Larastan inferring
    `mixed` on `X::factory()->create()` (12 phpstan errors). Promoted to Codebase Patterns.
  - Pint's `fully_qualified_strict_types` rewrites docblock `{@see \FQCN}` into real `use`
    imports. It made the event import its own action — cleaned by prose-referencing the action.
    Rule: `{@see}` downward refs only; prose for upward/peer deps you don't want imported.
  - `->sole()` is the clean event-row fetch: non-null (PHPStan-happy) AND asserts exactly-one in
    a single call, replacing a nullable `get()->first()`.
  - Under `RefreshDatabase` the action's own `DB::transaction` satisfies the recorder's level-0
    guard via the savepoint; the `afterCommit` delivery hook never fires (outer tx rolls back) —
    harmless here (no consumers registered for catalog `*Created` yet).
---
