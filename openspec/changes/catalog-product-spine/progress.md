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
- **Schema-absence guard (an attribute that must NOT exist — task 2.2, reused by 3.1/3.2/3.3).**
  When a spec rule is "entity X carries no Y" (Case Configuration has no breakability — BR-RefData-2;
  the neutral core holds no `appellation`/`vintage_year` — AC-0-GEN-2/3; a PR has no
  `case_configuration_id` — BR-Identity-3), assert the absence three ways: `Schema::hasColumn($table,
  $name)` is `false` for each forbidden name; `Schema::getColumnListing($table)` contains no column whose
  name carries the forbidden concept as a substring (loop `expect($col)->not->toContain('break')`); and
  the `*Created` payload omits the key (`expect($payload)->not->toHaveKey(...)`). The column-listing
  substring sweep is the strongest of the three (it catches a renamed-but-still-present attribute). Both
  `getColumnListing`/`hasColumn` are portable (verified on PG17). The absence IS the contract.
- **Multi-table entity (neutral core + per-type 1:1 attribute table — task 3.1, reused by 3.2).** TWO
  migrations: the core (`catalog_X`) + the per-type table (`catalog_X_wine_attributes`). The per-type table
  is the entity's OWN extension, so a within-module FK + relation is allowed (NOT the cross-module ban). Key
  mechanics: (a) the per-type table name is long — `->constrained(table: 'catalog_X', indexName: 'short_fk')`
  and `->index('col', 'short_idx')` with SHORT explicit names, else the framework auto-name exceeds **PG's
  63-char identifier limit** (silent truncation); (b) the core model gets a typed `hasOne`:
  `/** @return HasOne<XWineAttributes, $this> */` (use `$this` for the declaring model — the modern Larastan
  idiom; default keys work — FK `X_id`, local `id`); (c) the FACTORY auto-attaches the 1:1 in
  `afterCreating(fn (X $x) => $x->wineAttributes()->doesntExist() && $x->wineAttributes()->create([...]))` —
  the child takes the FK from the parent relation, never builds a parent ⇒ no recursion (so the per-type
  table needs NO factory of its own); (d) the action writes the child via `$x->wineAttributes()->create([...])`
  inside the same tx; (e) the `*Created` payload stays **core-only** (don't load the relation just to widen
  it — a consumer needing a per-type attr reads it through a published contract). AC-0-GEN-2/3 absence guard
  (schema-absence idiom above) proves the per-type column is NOT on the core but IS on the attrs table.
- **Two-source enum CHECK (a table with ≥2 enum string columns — task 3.1).** In the single `if
  (DB::getDriverName()==='pgsql')` block, emit ONE `ALTER TABLE … ADD CONSTRAINT catalog_X_<col>_check CHECK
  (<col> IN (…))` per enum column, each value list built from its own `Enum::cases()` (so neither can drift).
  Same layered idiom as `domain_events.actor_role`; the enum cast carries the floor on SQLite.
- **Localized domain rejection (first used 3.1 — invariant 12).** A module exception with a static
  constructor returning `new self((string) __('<group>.<key>', [...placeholders]))`. The `(string)` cast is
  load-bearing — Larastan types `__($key, …)` as **`mixed`** (non-null key → `mixed` in the helper stub), and
  the RuntimeException ctor wants `string`. Author ONLY the English baseline (`lang/en/<group>.php`, dotted
  nested keys); the other 5 locales fall back per-key (welcome.php convention) — do NOT author 6 files. Identity
  values in the message (name/appellation/producer id) are operator-facing, not PII.
- **Fail-closed type guard at a string boundary (task 3.1 — single-case enum).** When an enum has exactly one
  valid case (`ProductType::Wine`), a typed-enum param can't express the negative the spec wants rejected. Take
  `string $type = ProductType::Wine->value` and validate `$t = ProductType::tryFrom($type); if ($t !==
  ProductType::Wine) throw …;` — fail-closed at the input boundary, testable BOTH ways (`'wine'` accepted,
  `'beer'` rejected), with the PG CHECK as the DB backstop. Guard runs BEFORE the tx (pure input validation).
- **Two phpstan-max scaffolding traps (task 3.1).** (1) Faker `randomElement()` and `unique()->method()`
  return **`mixed`** (`@method mixed` / UniqueGenerator `__call`) → `mixed . ' '` and `ucfirst(mixed)` fail at
  max; use `@method string` providers (`word`/`sentence`/`name`/`company`/`lastName`/`city`/`country`) — verify
  the `@method string` in `vendor/fakerphp/faker/src/Faker/Generator.php`. (2) Chaining MULTIPLE
  `->not->toContain()` (or any matcher) on ONE `expect()` collapses the Expectation generic to `mixed` (the
  first matcher returns a non-generic Expectation, breaking the second `->not`) → one matcher per statement
  (nested `foreach`), or split with `->and($x)`.

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

## [2026-06-14 19:50] — 2.2 Case Configuration (catalog_case_configurations + model + factory + event + action)
- **What:** Second reference entity, applying the spine template verbatim. The
  `catalog_case_configurations` migration (id, name, `units_per_case`, `packaging_type`,
  `lifecycle_state` + driver-guarded PG CHECK, `version`, `timestampsTz`) **with no breakability column**
  + `CaseConfiguration` model + `CaseConfigurationFactory` + `CaseConfigurationCreated` event (const
  NAME/ENTITY_TYPE + static PII-free `payload()`) + `CreateCaseConfiguration` action (one `DB::transaction`:
  insert `draft` + record `CaseConfigurationCreated`). Adds the §7-stays-downstream guard test
  (BR-RefData-2 / AC-0-BR-RefData-2): the entity carries no breakability attribute or column.
- **Files changed:**
  - `database/migrations/2026_06_14_000002_create_catalog_case_configurations_table.php` (new)
  - `app/Modules/Catalog/Models/CaseConfiguration.php` (new)
  - `app/Modules/Catalog/Events/CaseConfigurationCreated.php` (new)
  - `app/Modules/Catalog/Actions/CreateCaseConfiguration.php` (new)
  - `database/factories/Catalog/CaseConfigurationFactory.php` (new)
  - `tests/Feature/Modules/Catalog/CaseConfigurationTest.php` (new — 5 tests, 32 assertions)
  - `openspec/changes/catalog-product-spine/tasks.md` (2.2 checked)
- **Quality loop:** green — pint clean · CaseConfigurationTest 5/5 (32 assertions) · full suite **267/267**
  (962 assertions, +5 vs the 262 baseline) · phpstan **0 @ max** · pint --test clean · `openspec validate
  catalog-product-spine --strict` valid · `git diff main -- composer.{json,lock}` empty.
  **PG17 cross-engine verified: 267/267 on `postgres:17`** (driver proof printed `DRIVER=pgsql` before the
  run — confirms real PG, not a silent SQLite fallback).
- **Learnings for future iterations:**
  - The spine template held with zero surprises — only the entity-specific columns/payload changed. The
    template (Codebase Patterns) is now proven twice; 3.1 (Master, the first multi-table + per-type-attrs
    + dedup entity) is where it stretches.
  - Added the **schema-absence guard** idiom to Codebase Patterns — the reusable shape for the
    "entity carries no Y" rules that 3.1 (no `appellation` in core), 3.2 (no `vintage_year` in core), and
    3.3 (no `case_configuration_id`) all repeat: `hasColumn` false + `getColumnListing` substring sweep +
    payload-key absence. The substring sweep is the strongest leg (catches a renamed-but-present column).
  - `packaging_type` stays a plain string (no `PackagingType` enum) — only `ProductType` earned an enum
    (design D2, §16 anti-EAV); a packaging enum would be speculative. Factory uses coherent
    loose/owc/carton tuples that line up with the 4.1 SellableSku test hint (loose/OWC6/CARTON12).
---

## [2026-06-14 20:13] — 3.1 Product Master (multi-table entity + dedup gate)
- **What:** The first MULTI-TABLE spine entity. Two migrations — `catalog_product_masters` (neutral core:
  `name`, `product_type` string + `ProductType` cast + driver-guarded PG CHECK, `producer_id` plain
  `unsignedBigInteger` **no FK/relation**, `lifecycle_state` + CHECK, `version`, `timestampsTz`, a
  `(producer_id, name)` index) + `catalog_product_master_wine_attributes` (1:1; `product_master_id` FK
  **within module** `->constrained(indexName: …)`, `appellation`, `region`, nullable `winery_story` json
  via `TranslatableTextCast`, `appellation` index). Models `ProductMaster` (within-module `hasOne`
  wineAttributes) + `ProductMasterWineAttributes`. `ProductMasterCreated` event (core-only PII-free
  payload). Two new localized domain exceptions (`DuplicateProductMasterIdentity`, `UnsupportedProductType`)
  + `lang/en/catalog.php` baseline. `CreateProductMaster` action: fail-closed non-WINE guard (string
  boundary) **before** the tx, then in ONE tx the BR-Identity-1 dedup (non-retired collision on
  producer+name+appellation via core⋈wine join → reject) then core+wine insert + record event. Factory
  auto-attaches the 1:1 wine attrs in `afterCreating`.
- **Files changed:** 2 migrations (000003/000004), `Models/ProductMaster.php`,
  `Models/ProductMasterWineAttributes.php`, `Events/ProductMasterCreated.php`,
  `Exceptions/{DuplicateProductMasterIdentity,UnsupportedProductType}.php`, `Actions/CreateProductMaster.php`,
  `database/factories/Catalog/ProductMasterFactory.php`, `lang/en/catalog.php`,
  `tests/Feature/Modules/Catalog/ProductMasterTest.php` (9 tests / 79 assertions), `tasks.md` (3.1 checked).
- **Quality loop:** green — pint clean · ProductMasterTest 9/9 · full suite **276/276** (1041 assertions,
  +9 vs 267) · phpstan **0 @ max** · pint --test clean · `openspec validate --strict` valid · `git diff main
  -- composer.{json,lock}` empty. **PG17 cross-engine VERIFIED: 276/276 on `postgres:17`** (driver proof
  `DRIVER=pgsql / SERVER=17.10`).
- **Learnings for future iterations:**
  - **Two phpstan-max scaffolding traps** (both promoted to Codebase Patterns): Faker `randomElement()` and
    `unique()->x()` return **`mixed`** (`@method mixed` / UniqueGenerator `__call`) → `mixed . ' '` fails;
    use `@method string` providers (`lastName`/`city`/`country`/`sentence`). And chaining **multiple**
    `->not->toContain()` on ONE `expect()` collapses the Expectation generic to `mixed` (the first
    `toContain` returns a non-generic Expectation) → one `toContain` per statement (nested loop).
  - The spine template held for the multi-table shape; the NEW pieces (per-type 1:1 table, two-source CHECK,
    localized rejection, within-module relation) are now in Codebase Patterns for 3.2/3.3 to copy verbatim.
  - 3.2 (Variant) is the SAME multi-table shape: copy 3.1 exactly — neutral `catalog_product_variants`
    (+ `product_master_id` FK within module, `variant_identifier`) + `catalog_product_variant_wine_attributes`
    (`vintage_year` nullable, `non_vintage` bool, `tasting_notes` json cast); assert `vintage_year` NOT on the
    core (AC-0-GEN-3); `belongsTo` master within module. No dedup, no fail-closed type guard (those are
    Master-specific). 3.3 (Reference) returns to a single-table entity + a DB unique `(variant, format)`.
---
