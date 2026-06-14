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
- **Within-module `belongsTo` (a child entity → its same-module parent — task 3.2, reused by 3.3/4.x).** The
  parent is in the SAME module, so a `belongsTo` is allowed (NOT the cross-module ban — that bars OTHER modules'
  tables). Mechanics: `/** @return BelongsTo<Parent, $this> */ public function master(): BelongsTo { return
  $this->belongsTo(Parent::class, 'parent_id'); }` (`$this` for the declaring model — the modern Larastan idiom,
  same as `HasOne`). Declare the dynamic prop `@property-read Parent|null $parent` (Larastan types a belongsTo
  getResult as nullable, matching the `HasOne|null` convention). In TESTS resolve via the RELATION METHOD —
  `$child->master()->sole()` returns a NON-NULL parent (PHPStan-happy + asserts exactly-one) — never the nullable
  dynamic `->master` property. The factory sets the FK with `'parent_id' => Parent::factory()` (a within-module
  nested factory — recursion-free because the parent factory never builds a child). The single scalar FK is the
  STRUCTURAL single-parent enforcement (BR-Identity-2); SQLite enforces it too (`DB_FOREIGN_KEYS` defaults true).
- **DB-enforced unique identity vs application dedup (task 3.3, reused by 4.2).** When an entity's identity is a
  SINGLE-table tuple (the PR's `(product_variant_id, format_id)`; 4.2's `(composite_sku_id, product_reference_id)`),
  enforce it with a DB `$table->unique([...cols], 'short_name')` and let the violation surface — NO application
  check, NO localized exception (contrast the Master's dedup, which spans TWO tables and so MUST be an in-action
  join check with a localized reason; a single-table tuple the DB can own outright). A duplicate insert throws
  `Illuminate\Database\UniqueConstraintViolationException` (extends `QueryException`), mapped reliably on BOTH
  engines (each connection implements `isUniqueConstraintError`; `Connection.php` throws the specific subclass).
  Test it via the action's OWN `DB::transaction` — that IS the savepoint (trap 5), so on PG the violation rolls
  back to the savepoint and the outer RefreshDatabase tx survives the follow-on assertions:
  `expect(fn () => app(CreateX::class)->handle(dupePair))->toThrow(UniqueConstraintViolationException::class)`, then
  assert `where(pair)->count() === 1` (resolves-to-one) AND that NO event was recorded (the insert aborts before
  the recorder runs). Prove the unique is COMPOSITE not single-column: same-A+different-B and different-A+same-B
  both succeed. The default unique-index auto-name runs ~62 chars (perilously near PG's 63 limit) → give a short
  explicit name (same rule as the long FK names).
- **FK onDelete asymmetry — owner cascades, shared reference restricts (task 3.3).** `cascadeOnDelete()` ONLY from
  the OWNING parent in the identity subtree (the PR cascades from its Variant: Master→Variant→PR; the Variant from
  its Master) — deleting a parent reaps its children. A SHARED reference dimension takes the framework DEFAULT
  (restrict / NO ACTION): `format_id` has no `cascadeOnDelete()`, so a Format referenced by many PRs can't be
  deleted out from under them. Decide per FK by ownership; never blanket-cascade.
- **`Schema::getColumnListing()` loses its `list<string>` type through the FACADE (task 3.2 phpstan trap).** The
  Builder method is `@return list<string>`, but Larastan resolves the `Schema` FACADE call to `mixed`-valued
  elements — so `array_filter($cols, fn (string $c) => …)` FAILS at max (`array_filter` demands `callable(mixed)`;
  a `string`-typed closure is contravariantly too narrow). Two clean idioms: (a) a `foreach ($cols as $col)`
  passing `$col` only to `expect()` (mixed-accepting — the 3.1 absence-sweep), or (b) for an exact-set / single-FK
  proof, `sort($cols); expect($cols)->toBe([...alphabetical...])` — order-independent and cross-engine stable (PG
  & SQLite both list columns in creation/ordinal order; sorting removes the dependence). Do NOT call a string
  builtin (`str_contains`) on a raw element, and do NOT cast `(string) $col` to silence it.
- **Two phpstan-max scaffolding traps (task 3.1).** (1) Faker `randomElement()` and `unique()->method()`
  return **`mixed`** (`@method mixed` / UniqueGenerator `__call`) → `mixed . ' '` and `ucfirst(mixed)` fail at
  max; use `@method string` providers (`word`/`sentence`/`name`/`company`/`lastName`/`city`/`country`) — verify
  the `@method string` in `vendor/fakerphp/faker/src/Faker/Generator.php`. (2) Chaining MULTIPLE
  `->not->toContain()` (or any matcher) on ONE `expect()` collapses the Expectation generic to `mixed` (the
  first matcher returns a non-generic Expectation, breaking the second `->not`) → one matcher per statement
  (nested `foreach`), or split with `->and($x)`.
- **Event-class name vs model-class name can DIVERGE (task 4.1, reused by 4.2 + the 5.1 guard).** The `*Created`
  event class is named VERBATIM per §14.1 (design D7) — which for the SKUs keeps `SKU` UPPER-case: the events are
  `SellableSKUCreated` / `CompositeSKUCreated` (the event class name AND `const NAME` both upper-`SKU`), while the
  canonical MODEL classes are `SellableSku` / `CompositeSku` (§18 cascade, lower-`ku`). So `ENTITY_TYPE` (the
  envelope `entity_type` = the model's short class name, by the established convention) is `'SellableSku'` even
  though `NAME` is `'SellableSKUCreated'` — they legitimately differ in casing. The other five spine events
  coincide (class == NAME) only because their §14.1 names are already PascalCase-clean. The naming-cascade guard
  (5.1) must therefore `class_exists()` the UPPER-`SKU` EVENT names (`SellableSKUCreated`, `CompositeSKUCreated`)
  and the lower-`Sku` MODEL names (`SellableSku`, `CompositeSku`) — do not assume event class == model class.
- **Spec fidelity over the i18n reflex (task 4.1).** A customer-facing-SOUNDING text field is NOT automatically a
  `TranslatableText` — check whether §8.1 actually scopes translatability to it. The SKU's `commercial_name` /
  `marketing_copy` (§3.7) are PLAIN string/text columns because §8.1 places translatable content on
  Master/Variant/PR only and is SILENT on the SKU; making them translatable would invent beyond the spec.
  Invariant 12 ("no hardcoded user-facing strings") governs CODE strings, not data-column shape — which columns
  are translatable is a spec-driven modelling choice, decided per the §8.1 list, not by how customer-facing a
  field feels. (Ground the column set in the PRD: §3.7 names exactly "commercial name, marketing copy".)
- **M:N entity = parent table + pure link table + ordered `belongsToMany` (task 4.2).** When an entity's content
  IS a many-to-many set (the Composite's constituents), split into the parent (`catalog_composite_skus` —
  lifecycle + audit ONLY, no business columns; §3.8 "cheap at PIM, registration + lifecycle only") and a join
  table (`catalog_composite_sku_constituents`): `owner_id` FK **cascade** (the link belongs to the parent) +
  `referenced_id` FK **restrict** (the shared atomic key — FK onDelete asymmetry again) + `position`
  (`unsignedInteger`) + a DB **`unique([owner_id, referenced_id], 'short')`** (the per-pair idiom from 3.3 — makes
  the set distinct; the same referenced row may still recur across DIFFERENT owners). The join is a PURE link
  table: **no surrogate `id()`, no timestamps** — a `belongsToMany` pivot needs neither, the natural key is the
  unique pair, the audit lives on the parent + its `*Created` event. Index names: the long join-table name
  overflows PG's 63-char limit on EVERY auto-name → abbreviate (`catalog_csc_*`). Relation:
  `/** @return BelongsToMany<Related, $this> */` (2 generics — `TRelatedModel, TDeclaringModel`, verified in
  vendor; same `$this` idiom as belongsTo) `belongsToMany(Related::class, 'join_table', 'owner_fk',
  'related_fk')->withPivot('position')->orderByPivot('position')` (both `orderByPivot`/`withPivot` live on
  `BelongsToMany`, not the Concerns trait — verified in vendor). Declare `@property-read
  \Illuminate\Database\Eloquent\Collection<int, Related> $constituents` (a docblock-only import survives Pint —
  the models already prove it with `CarbonInterface`). Factory attaches the set in `configure()->afterCreating`
  with a `doesntExist()` guard (mirrors the 3.1 wine-attrs idiom): `attach([Related::factory()->create()->id =>
  ['position' => 1], …])`. Action: dedupe+order the input (`array_values(array_unique($ids))`), then a single
  keyed `attach([$id => ['position' => $i + 1], …])` inside the tx (one INSERT, contiguous 1..N positions).
- **A cross-ROW count rule (N ≥ K) is a pre-tx localized rejection, NOT a DB constraint (task 4.2, N ≥ 2).** When
  admissibility depends on COUNTING rows (the Composite needs ≥ 2 distinct constituents), it can't be a column
  CHECK or a single-pair unique — it's an in-action guard like the Master's dedup, BUT it's PURE INPUT VALIDATION
  (count the input array, no DB query) so it runs BEFORE `DB::transaction` (like the Master's fail-closed type
  guard), leaving NO parent row + NO event on rejection (assert `Model::query()->count() === 0`). Count over the
  DISTINCT set when a DB unique makes the relation a set (`count(array_unique($ids)) < 2`), so the guard aligns
  with what actually persists — `[A, A]` is ONE constituent, rejected; `[A, A, B]` collapses to 2, accepted.
  Throw a localized `RuntimeException` subclass (`InsufficientCompositeConstituents::forCount($n)` → `(string)
  __('catalog.composite_sku.…', ['count' => $n])`; English baseline only, per-key fallback — the 3.1 idiom).
- **A producer-agnostic non-check is a CONTRACT — test its ABSENCE (task 4.2, BR-SKU-5 / design D9).** The spec
  REQUIRES that PIM not validate producer composition (single-producer admissibility is Module S's Offer rule).
  A "helpful" producer-uniformity guard would be a boundary violation. Prove the absence: build constituents whose
  Masters carry DIFFERENT `producer_id` (plain column — build the chain explicitly: `ProductMaster::factory()
  ->create(['producer_id' => 1001])` → Variant override `['product_master_id' => …]` → PR override
  `['product_variant_id' => …]`) and assert the Composite is ACCEPTED (creation succeeds, event recorded). The
  succeeding-with-a-multi-producer-set IS the proof no producer guard ran.
- **Event-vs-model name divergence held for the SECOND SKU (task 4.2 confirms 4.1).** `CompositeSKUCreated`
  (UPPER-`SKU`, `const NAME` + class name verbatim §14.1 / delta-spec line 164) vs model `CompositeSku` (§18);
  `ENTITY_TYPE = 'CompositeSku'` (model short name). The 5.1 naming-cascade guard `class_exists()`s the
  UPPER-`SKU` EVENTS (`SellableSKUCreated`, `CompositeSKUCreated`) + lower-`Sku` MODELS (`SellableSku`,
  `CompositeSku`) — both SKU pairs now exist to assert against.
- **Naming-cascade / category-neutral arch guard (task 5.1 — reusable for every module's §18 discipline).** A
  convention test that pins a naming cascade has THREE legs, all boot-free (reflect `Module::X->namespace()` /
  `->name`, no Laravel container — the `ModuleConformanceTest`/`ModulePersistenceConventionsTest` idiom): (1)
  **positive existence** — `class_exists()` every canonical model + every `*Created` event FQCN (collect missing →
  `toBe([])`); guard the SET SIZE too (`toHaveCount(7)`) so a silent drop from the list is caught not masked. (2)
  **negative scan** — walk the module subtree (`RecursiveIteratorIterator` over the `Module::X->name` dir), collect
  each `.php` `getBasename('.php')` (PSR-4: the FILENAME *is* the class short-name, so ONE sweep covers
  Models/Events/Actions/Enums/Exceptions — "class OR event name"), then `array_filter` for the forbidden pattern →
  `toBe([])`. (3) **alias-retention** — reflect `getDocComment()` on the aliased models and assert the display-alias
  phrase is PRESENT (the spec's "retained ONLY as a wine-display alias": the negative scan bars it from code
  identifiers, this pins its one legal home — the docblock; together they encode "only as a display alias"). **Anchor
  the forbidden regex** (`/^(Wine|BottleReference)/`, NOT a loose `/Wine/` substring): a category-neutral core
  legitimately carries per-type SUFFIX-qualified classes (`ProductMasterWineAttributes` / `ProductVariantWineAttributes`,
  blessed by design D1's `catalog_*_wine_attributes` tables) that CONTAIN the category word — only a category-PREFIXED
  spine rename (`WineMaster`, `BottleReference`) is forbidden. **Prove non-vacuity THROUGH the tricky case:** assert the
  scan `toContain('ProductMasterWineAttributes')` — that exercises the anchoring (a regression to `/Wine/` turns it red),
  far stronger than a bare "saw some files". `array_filter` over a self-built `list<string>` (not the `Schema` facade)
  keeps a `fn (string $name)` closure phpstan-max-clean. NO DB → no PG run (test-only + docblocks).

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

## [2026-06-14 20:29] — 3.2 Product Variant (multi-table entity + within-module belongsTo)
- **What:** The second MULTI-TABLE spine entity — the 3.1 shape copied verbatim, minus the Master-specific
  dedup + fail-closed type guard, plus a within-module `belongsTo` to the parent Master. Two migrations:
  `catalog_product_variants` (neutral core: single-parent `product_master_id` FK within module, type-neutral
  `variant_identifier`, `lifecycle_state` + SINGLE-source driver-guarded PG CHECK — no `product_type` on the
  Variant, the type is fixed by the Master — `version`, `timestampsTz`) + `catalog_product_variant_wine_attributes`
  (1:1; `vintage_year` nullable int, `non_vintage` bool default false, `tasting_notes` json via TranslatableTextCast;
  short explicit FK name `catalog_pv_wine_attrs_variant_fk` — the auto-name overflows PG's 63-char limit). Models
  `ProductVariant` (within-module `hasOne` wineAttributes + `belongsTo` master) + `ProductVariantWineAttributes`.
  `ProductVariantCreated` event (core-only PII-free payload; parent Master by id). `CreateProductVariant` action
  (one tx: insert core + wine attrs via relation + record event; NO dedup, NO type guard). Factory auto-attaches
  the 1:1 wine attrs in `afterCreating`, parent Master via `ProductMaster::factory()` (within-module).
- **Files changed:** 2 migrations (000005/000006), `Models/ProductVariant.php`, `Models/ProductVariantWineAttributes.php`,
  `Events/ProductVariantCreated.php`, `Actions/CreateProductVariant.php`, `database/factories/Catalog/ProductVariantFactory.php`,
  `tests/Feature/Modules/Catalog/ProductVariantTest.php` (8 tests / 54 assertions), `tasks.md` (3.2 checked).
- **Quality loop:** green — pint clean · ProductVariantTest 8/8 (54 assertions) · full suite **284/284** (1095
  assertions, +8 vs 276) · phpstan **0 @ max** · pint --test clean · `openspec validate --strict` valid · `git diff
  main -- composer.{json,lock}` empty · `ModuleBoundariesTest` 2/2 (no amendment). **PG17 cross-engine VERIFIED:
  284/284 on `postgres:17`** (driver proof `DRIVER=pgsql SERVER=17.10`); container cleaned up.
- **Learnings for future iterations:**
  - The multi-table template held verbatim; the only NEW pieces (both promoted to Codebase Patterns): the
    within-module `belongsTo` idiom (`BelongsTo<Parent, $this>`, resolve via `master()->sole()` in tests to dodge
    the nullable-property phpstan error, factory FK via `Parent::factory()`), and the `getColumnListing` facade
    type-loss trap (the facade returns `mixed`-valued elements, so `array_filter` with a `string` closure fails at
    max — use a `sort()`+`toBe([...])` exact-set assertion or the per-element `expect()` foreach).
  - The Variant CHECK is SINGLE-source (only `lifecycle_state`) — no `product_type` column on the Variant core
    (the type is the Master's). Format/CaseConfiguration also single-source; only the Master is two-source.
  - 3.3 (Product Reference) is the NEXT task and a SHAPE CHANGE: back to a SINGLE-table entity (no per-type attrs)
    with TWO within-module FKs (`product_variant_id`, `format_id`) + a DB **unique `(product_variant_id, format_id)`**
    + **no `case_configuration_id`** (BR-Identity-3 absence guard). Test the unique violation inside a nested
    `DB::transaction` (savepoint — trap 5, so the verify-after-throw survives on PG). Two `belongsTo` (variant +
    format) within module. No dedup join, no per-type table.
---

## [2026-06-14 20:41] — 3.3 Product Reference (single-table entity + DB-unique identity)
- **What:** The SHAPE CHANGE the prior iteration flagged — back to a SINGLE-table spine entity (no per-type
  attribute table), but the FIRST entity whose identity is a DB-enforced composite UNIQUE. One migration
  `catalog_product_references`: two WITHIN-module FKs (`product_variant_id` cascade-on-delete — the PR is in the
  Variant's identity subtree; `format_id` restrict/default — Format is a shared reference dimension), a DB
  `unique(['product_variant_id','format_id'], 'catalog_product_references_variant_format_unique')` (BR-Identity-3
  — the two-dimension identity), **NO `case_configuration_id`**, `lifecycle_state` + single-source driver-guarded
  PG CHECK, `version`, `timestampsTz`. All three explicit index names kept short (FKs `…_variant_fk`/`…_format_fk`,
  the unique `…_variant_format_unique`) — the default unique auto-name runs ~62 chars, perilously near PG's 63
  limit. Model `ProductReference` (two within-module `belongsTo`: variant + format). `ProductReferenceCreated`
  event (PII-free: the two identity dimensions by id). `CreateProductReference` action — thin like the Variant's
  (one tx: insert `draft` + record event), NO application dedup and NO localized exception: a duplicate pair is
  rejected by the DB unique index, surfacing as `UniqueConstraintViolationException`. Factory builds both parents
  via their within-module factories (recursion-free; no `afterCreating` — single table).
- **Files changed:** migration `…000007_create_catalog_product_references_table.php`, `Models/ProductReference.php`,
  `Events/ProductReferenceCreated.php`, `Actions/CreateProductReference.php`,
  `database/factories/Catalog/ProductReferenceFactory.php`,
  `tests/Feature/Modules/Catalog/ProductReferenceTest.php` (8 tests / 42 assertions), `tasks.md` (3.3 checked).
- **Quality loop:** green — pint clean · ProductReferenceTest 8/8 (42 assertions) · full suite **292/292** (1137
  assertions, +8 vs 284) · phpstan **0 @ max** · pint --test clean · `openspec validate --strict` valid · `git diff
  main -- composer.{json,lock}` empty · `ModuleBoundariesTest` 2/2 (no amendment). **PG17 cross-engine VERIFIED:
  292/292 on `postgres:17`** (driver proof `DRIVER=pgsql SERVER=17.10`); container cleaned up.
- **Learnings for future iterations:**
  - The single-table template + two within-module `belongsTo` held with zero surprises (Format's single-table
    shape + the Variant's `belongsTo` idiom, both already in Codebase Patterns). The genuinely NEW piece — promoted
    to Codebase Patterns — is **DB-enforced unique identity vs application dedup**: a single-table identity tuple is
    a DB `unique(...)` that throws `UniqueConstraintViolationException` (reliable on both engines), tested through
    the action's own `DB::transaction` (the savepoint, trap 5), asserting resolves-to-one + no-event-recorded; and
    the composite-not-single-column proof (same-A+diff-B and diff-A+same-B both succeed). Contrast: the Master's
    identity spans TWO tables → an in-action join check with a localized reason. Pick by whether the tuple is
    single-table (DB owns it) or cross-table (the action must).
  - Also promoted: the **FK onDelete asymmetry** — cascade only from the owning parent (PR←Variant←Master),
    restrict (default) for a shared reference (Format). Avoids a Format delete silently reaping PRs.
  - Pint's `fully_qualified_strict_types` again pulled a docblock `{@see \FQCN}` into a real `use` import (here the
    vendor `UniqueConstraintViolationException` — harmless, no use-cycle since it's not a peer/upward module class).
  - 4.1 (Sellable SKU Intrinsic) is NEXT and the FIRST entity to reference BOTH a PR and a Case Configuration
    (`product_reference_id` + `case_configuration_id` FKs + commercial attrs, e.g. `commercial_name`). Single-table,
    two within-module `belongsTo`, no DB unique on identity (a Variant+Format+CaseConfig may yield many SKUs). Its
    test completes the "Packaging does not change the PR" scenario: three Case Configs → three SKUs → assert all
    three `product_reference_id` equal (the one PR from 3.3). 4.2 (Composite) then reuses the DB-unique idiom on the
    constituents join + the N≥2 / producer-agnostic rules (design D9).
---

## [2026-06-14 20:58] — 4.1 Sellable SKU (Intrinsic)
- **What:** The first entity to reference BOTH a Product Reference AND a Case Configuration — the single-table
  template (Format's shape) + two within-module `belongsTo` (the PR's idiom), nothing structurally new. Migration
  `catalog_sellable_skus`: `product_reference_id` FK **cascade-on-delete** (the SKU is a commercial composition
  owned by its PR — same asymmetry as PR←Variant) + `case_configuration_id` FK **restrict/default** (Case
  Configuration is a standalone SHARED reference, like Format), the §3.7 commercial attributes `commercial_name`
  (required) + `marketing_copy` (nullable text) as PLAIN columns (NOT TranslatableText — §8.1 scopes
  translatability to Master/Variant/PR, silent on the SKU), `lifecycle_state` + single-source driver-guarded PG
  CHECK, `version`, `timestampsTz`, and deliberately **NO DB unique** on `(product_reference_id,
  case_configuration_id)` (the spec defines no SKU uniqueness rule; packaging variants legitimately back many SKUs
  over one PR). Model `SellableSku` (two within-module `belongsTo`: `reference()` + `caseConfiguration()`); event
  `SellableSKUCreated` (verbatim §14.1 — UPPER-`SKU`; `NAME='SellableSKUCreated'`, `ENTITY_TYPE='SellableSku'`,
  PII-free payload = ids + `commercial_name`, `marketing_copy` omitted as lean); `CreateSellableSku` action (thin
  — one tx: insert `draft` + record event; NO dedup, NO type guard, NO activation-prereq — those are deferred).
  Factory builds both parents via their within-module factories (recursion-free, no `afterCreating`).
- **Files changed:** migration `…000008_create_catalog_sellable_skus_table.php`, `Models/SellableSku.php`,
  `Events/SellableSKUCreated.php`, `Actions/CreateSellableSku.php`,
  `database/factories/Catalog/SellableSkuFactory.php`,
  `tests/Feature/Modules/Catalog/SellableSkuTest.php` (8 tests / 34 assertions), `tasks.md` (4.1 checked).
- **Quality loop:** green — pint clean · SellableSkuTest 8/8 (34 assertions) · full suite **300/300** (1171
  assertions, +8 vs 292) · phpstan **0 @ max** · pint --test clean · `openspec validate --strict` valid · `git diff
  main -- composer.{json,lock}` empty · `ModuleBoundariesTest` 2/2 (no amendment — the SKU's two FKs are
  within-module; no producer ref). **PG17 cross-engine VERIFIED: 300/300 on `postgres:17`** (driver proof
  `DRIVER=pgsql SERVER=17.10`); container cleaned up.
- **Learnings for future iterations:**
  - Two patterns promoted to Codebase Patterns: (1) **event-class name vs model-class name can diverge** — the SKU
    events are `SellableSKUCreated`/`CompositeSKUCreated` (UPPER-`SKU`, verbatim §14.1) while the model classes are
    `SellableSku`/`CompositeSku` (§18); `ENTITY_TYPE` follows the model (`'SellableSku'`), `NAME` follows §14.1.
    The 5.1 naming-cascade guard must `class_exists()` the UPPER-`SKU` events + lower-`Sku` models — don't assume
    event class == model class. (2) **Spec fidelity over the i18n reflex** — `commercial_name`/`marketing_copy` are
    plain columns, not TranslatableText, because §8.1 scopes translatability to Master/Variant/PR and is silent on
    the SKU; grounded the exact column set in PRD §3.7 ("commercial name, marketing copy") via a subagent rather
    than guessing.
  - The "Packaging does not change the PR" scenario is now COMPLETE across 3.3 + 4.1: 3.3 proved the `(variant,
    format)` pair is unique (one PR); 4.1 proved three Case Configurations over that one PR yield three SKUs all
    sharing the one `product_reference_id` (PR count stays 1).
  - 4.2 (Composite SKU) is NEXT — the LAST entity + a join table. TWO migrations: `catalog_composite_skus` (just
    `lifecycle_state` + audit/version — no FKs on the parent) + `catalog_composite_sku_constituents`
    (`composite_sku_id` FK cascade, `product_reference_id` FK restrict, `position`, **DB unique
    `(composite_sku_id, product_reference_id)`** — reuse the 3.3 DB-unique idiom). Model `CompositeSku`
    (constituents M:N ordered by `position`); event `CompositeSKUCreated` (UPPER-`SKU` again — see the new pattern);
    `CreateCompositeSku` action enforcing **N ≥ 2** (a localized rejection like the Master's — cross-row count, not
    a DB constraint) and DELIBERATELY NOT validating producer composition (design D9 / BR-SKU-5 — a multi-producer
    set is ACCEPTED; do NOT add a single-producer guard, that's Module S). Test: <2 constituents rejected;
    multi-producer accepted; one PR in two Composites (M:N). Verify on PG17.
---

## [2026-06-14 21:15] — 4.2 Composite SKU (last entity + first M:N join table)
- **What:** The seventh and LAST spine entity, and the spine's only many-to-many. TWO migrations:
  `catalog_composite_skus` (parent — `lifecycle_state` + driver-guarded PG CHECK + `version` + `timestampsTz`,
  and NOTHING else: §3.8 keeps the Composite "cheap at PIM, registration + lifecycle only" — confirmed no
  commercial attrs via an Explore subagent over PRD §3.8, the 4.1 ground-it-don't-guess discipline) +
  `catalog_composite_sku_constituents` (PURE link table — `composite_sku_id` FK **cascade**,
  `product_reference_id` FK **restrict**, `position`, DB unique `(composite_sku_id, product_reference_id)`; NO
  surrogate id, NO timestamps; abbreviated index names `catalog_csc_*` because the long join-table name overflows
  PG's 63-char limit on every auto-name). Model `CompositeSku` (`constituents()` ordered `belongsToMany` over the
  join with `withPivot('position')->orderByPivot('position')`). Event `CompositeSKUCreated` (UPPER-`SKU` verbatim
  §14.1 / delta-spec line 164; `ENTITY_TYPE='CompositeSku'`; PII-free payload = id + ordered constituent PR ids +
  count + state). Exception `InsufficientCompositeConstituents` (localized) + `catalog.composite_sku.*` lang key.
  `CreateCompositeSku` action: dedupe+order input → **N ≥ 2 over the DISTINCT set, pre-tx** localized rejection →
  in one tx insert parent (`draft`) + a single keyed `attach()` (contiguous 1..N positions) + record event.
  **DELIBERATELY no producer check** (design D9 / BR-SKU-5). Factory attaches 2 constituents in
  `configure()->afterCreating` (`doesntExist()` guard).
- **Files changed:** 2 migrations (000009 parent / 000010 join), `Models/CompositeSku.php`,
  `Events/CompositeSKUCreated.php`, `Exceptions/InsufficientCompositeConstituents.php`,
  `Actions/CreateCompositeSku.php`, `database/factories/Catalog/CompositeSkuFactory.php`, `lang/en/catalog.php`
  (+`composite_sku` group), `tests/Feature/Modules/Catalog/CompositeSkuTest.php` (12 tests / 40 assertions),
  `tasks.md` (4.2 checked).
- **Quality loop:** green — pint clean · CompositeSkuTest 12/12 (40 assertions) · full suite **312/312** (1211
  assertions, +12 vs 300) · phpstan **0 @ max** · pint --test clean · `openspec validate --strict` valid · `git
  diff main -- composer.{json,lock}` empty · `ModuleBoundariesTest` 2/2 (no amendment — the join's two FKs are
  within-module; no producer ref). **PG17 cross-engine VERIFIED: 312/312 on `postgres:17`** (driver proof
  `DRIVER=pgsql SERVER=17.10`); container cleaned up.
- **Learnings for future iterations:**
  - Four patterns promoted to Codebase Patterns: (1) **M:N = parent + pure link table + ordered `belongsToMany`**
    (link table needs no id/timestamps; `BelongsToMany<Related,$this>` 2-generic; `orderByPivot`/`withPivot` on
    the relation; abbreviate index names); (2) **a cross-ROW count rule (N≥K) is a PRE-tx localized rejection**
    (pure input validation → before the tx → no orphan row/event; count the DISTINCT set when a unique makes it a
    set); (3) **a producer-agnostic non-check is a CONTRACT — test its ABSENCE** (multi-producer set accepted is
    the proof); (4) the **event-vs-model name divergence** held for the second SKU (`CompositeSKUCreated` upper /
    `CompositeSku` model).
  - The §3.8/delta-spec tension (PRD silent on ordering + within-composite uniqueness, but the APPROVED change
    requires `position` + the `(composite, PR)` unique) resolves in favour of the change artifacts (RALPH: "the
    change artifacts ARE the plan"); silence ≠ prohibition, so the richer ordered-distinct-set model is faithful.
  - **ALL 7 spine entities now exist.** 5.1 (naming-cascade guard) is NEXT — a convention/arch test, NO new DB:
    `class_exists()` the 7 canonical models (`ProductMaster`, `ProductVariant`, `ProductReference`, `Format`,
    `CaseConfiguration`, `SellableSku`, `CompositeSku`) + the 7 `*Created` events (mind the UPPER-`SKU` events
    `SellableSKUCreated`/`CompositeSKUCreated`); assert NO Catalog class/event name matches `/Wine|BottleReference/`
    as a structural identifier; add wine-display-alias docblocks. Then 5.2 (docs — CONTEXT.md glossary +
    event-contract note) and 5.3 (full-chain integration + final cross-engine close).
---

## [2026-06-14 21:25] — 5.1 Naming-cascade guard (convention arch test + alias pinning)
- **What:** A pure CONVENTION/architecture test (NO new DB) pinning the §18 naming cascade as the canonical Catalog
  code naming (design D7; AC-0-GEN-6; delta-spec "Naming Cascade" requirement). New file
  `tests/Architecture/CatalogNamingCascadeTest.php` — three boot-free legs (mirroring the
  `ModuleConformanceTest`/`ModulePersistenceConventionsTest` reflection-rooted style): (1) **positive existence** —
  `class_exists()` the 7 canonical models (`ProductMaster`, `ProductVariant`, `ProductReference`, `Format`,
  `CaseConfiguration`, `SellableSku`, `CompositeSku`) + the 7 `*Created` events (minding the UPPER-`SKU` divergence:
  `SellableSKUCreated`/`CompositeSKUCreated` events vs lower-`Sku` models), with `toHaveCount(7)` set-size guards; (2)
  **negative scan** — a recursive walk of the Catalog subtree collecting every `.php` basename (PSR-4 short-name) and
  `array_filter`ing for the forbidden category-PREFIX pattern `/^(Wine|BottleReference)/` → `toBe([])`, with
  non-vacuity proven THROUGH the tricky case (`toContain('ProductMasterWineAttributes')` — the suffix-qualified
  per-type class that CONTAINS "Wine" yet must stay legal); (3) **alias-retention** — reflect `getDocComment()` on the
  3 aliased models and assert each carries its wine-display alias ("Wine Master"/"Wine Variant"/"Bottle Reference (BR)").
  The docblock aliases were ALREADY present (added in 3.1–3.3), so this task adds no model edits — the test PINS them
  (the negative scan bars the alias from code identifiers; the alias-retention leg fixes its one legal home, the
  docblock — together encoding the spec's "retained ONLY as a wine-display alias").
- **Files changed:** `tests/Architecture/CatalogNamingCascadeTest.php` (new — 3 tests / 8 assertions), `tasks.md`
  (5.1 checked). NO production-code change (the model docblocks already carried their aliases).
- **Quality loop:** green — pint clean · CatalogNamingCascadeTest 3/3 (8 assertions) · full suite **315/315** (1219
  assertions, +3 tests vs 312) · phpstan **0 @ max** · pint --test clean · `openspec validate --strict` valid · `git
  diff main -- composer.{json,lock}` empty · `ModuleBoundariesTest` 2/2 (no amendment — test-only + docblocks). NO PG
  run: this task adds no schema (the hot-cache plan — 5.1/5.2 are DB-free; 5.3 is the final cross-engine close).
- **Learnings for future iterations:**
  - The genuinely NEW, reusable thing (promoted to Codebase Patterns): the **naming-cascade / category-neutral arch
    guard** — the 3-leg boot-free convention test (positive `class_exists`+count, negative basename scan, docblock
    alias-retention). Two decisions worth keeping: **anchor the forbidden regex** (`/^(Wine|BottleReference)/`, not the
    test-hint's loose `/Wine/`) because the category-neutral core legitimately carries per-type SUFFIX classes
    (`ProductMasterWineAttributes`) that the loose form would wrongly flag — the spec's forbidden set is literally
    `WineMaster*`/`WineVariant*`/`BottleReference*` (prefixes); and **prove non-vacuity through the tricky case**
    (`toContain('ProductMasterWineAttributes')`) so a regression to the loose regex turns the test red.
  - The implementation work was light because the prior tasks did the §18 naming RIGHT from the start (canonical
    `Product*` models, verbatim §14.1 events, docblock aliases) — 5.1 just made the discipline mechanical/enforced.
  - 5.2 (docs — CONTEXT.md spine glossary + the 7-event payload-contract note) is NEXT and also DB-free (docs-only — run
    lint/format, no test, `openspec validate --strict`). Then 5.3 (full-chain integration Master→Variant→Format→Reference
    →Intrinsic SKU + Composite, asserting all `*Created` / zero `*Activated`/`*Retired`) carries the FINAL full-Catalog
    cross-engine PG17 close.
---
