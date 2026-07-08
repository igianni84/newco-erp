# Testing — Hypotheses (test when possible)

> A hypothesis carries `Confirmations: N/3`. Confirmed 3× (dated) → promote to `rules.md`. Contradicted → note the contradiction and demote.

## Prove a class is ABSENT by listing the directory at runtime — never `class_exists('<literal>')` (PHPStan max flags it impossible)

**Hypothesis.** A scope guard that asserts a class / Action / Event does **not** exist must not use `class_exists('App\…\Absent')` with a string literal — PHPStan max evaluates the literal and flags the call `function.impossibleType` ("always false", dead code). Instead list the directory at runtime — `glob(app_path('Modules/<M>/<Dir>/*.php')) ?: []` (the `?: []` is required: `glob` returns `list<string>|false` and `array_map` rejects `false`), or a `RecursiveIteratorIterator` — map to basenames, and assert the **set** (`expect($names)->not->toContain('Absent')`, `toEqualCanonicalizing([...])`). A `class_exists($var)` on a variable derived from the listing is fine (not a resolvable literal). Bonus: a directory scan also catches a renamed-but-still-present class that a fixed `class_exists` list would miss.

**Confirmations: 2/3** (need 1 more distinct change).
- 2026-06-15 `catalog-product-spine` — `CatalogNamingCascadeTest` proves absence of a forbidden naming pattern by a recursive walk of the Catalog subtree (`getBasename('.php')` → `array_filter` → `toBe([])`).
- 2026-06-16 `parties-producer-lifecycle` — `SupplyLifecycleChainTest` states the rule + the PHPStan reason explicitly; `glob(...) ?: []` scope-guards both `Actions/` (only the six supply transitions) and `Events/` (no demand-side event exists).
- *(Related ancestor, NOT counted: 2026-06-12 `foundations-modules-skeleton` `ModuleConformanceTest` scans the modules dir for set-equality — same directory-scan idiom, but its driver is "no stray entries", not the `class_exists`-is-impossible mechanism.)*
- *(Related mechanism, NOT counted: `catalog-module-0-completeness-sweep` task 5.2 — `is_a(A::class, B::class, allow_string: true)` asserting two exception classes are unrelated drew the SAME `function.impossibleType`. Same root — PHPStan max resolves class-name literals, so any runtime check of a **static** class relation is dead code — but the prescription differs: there is no runtime substitute to reach for, because the type system has already proven it. **Delete the assertion**; where the relation carries real meaning, let `toThrow(Class::class, 'message fragment')` carry it and prove it bites by mutation. Generalisation to watch: the hypothesis is really "PHPStan max proving your test assertion means the assertion is not a test" — the directory-scan is one prescription of several.)*

**Applies to.** Any test that proves a class / Action / Event is absent (a category-neutrality or scope guard).

## A red-green FSM state-shape flip must invert EVERY observer in the SAME commit — enumerate by grep, sort into four kinds; the isolated writer's own contract + the source-scan guards stay diff-free

**Hypothesis.** When a change alters a state-machine's *shape* (a durable resting state becomes a transient pass-through, two steps merge into one atomic transition, a state is removed), every test that observed the OLD shape must be inverted in the **same commit** so the suite never goes red mid-change. Enumerate them exhaustively by grepping the changed state token + the driving Action's class name across `tests/` **before** editing — the design's enumeration is a checklist, the grep is the proof it is complete. Sort the hits into four kinds, each with a distinct action:
- **(a) outcome observers** — assert state/events AFTER the real Action → **flip** the expected terminal state + the event multiset.
- **(b) precondition helpers** — double-drove the old two-step only to *set up* a later state → **delete** the now-illegal second call (no try/catch); the collapsed Action reaches the terminal state alone.
- **(c) factory-forced-state datasets** — force the state via the factory and never call the flipped Action → **leave untouched** (they exercise the isolated writer, not the flow).
- **(d) source-scan guards** (exact-set Action allow-list, OC/side-write count, creation-chain event set) → **stay diff-free** *iff* no Action/Event class is added/renamed and the literal side-write count is unchanged; a pure shape-collapse rides existing guards without amending them.

The isolated writer's own contract test (factory-forced `From → To` + the illegal-from-state cases) also **stands unedited** — it never observed the flow, only the writer. Verify the whole set green on BOTH engines in one commit.

**Confirmations: 1/3** (need 2 more distinct changes).
- `parties-membership-charge-on-approval` (RM-03 / MVP-DEC-016) — the atomic *approve = charge = activation* collapse: `Approved` durable→transient. 8 outcome observers flipped + 4 precondition helpers' `ActivateProfile` line deleted; `ProfileActivationTest` (isolated writer) + `SupplyLifecycleChainTest` / `ComplianceIndependenceTest` / `SpineCreationChainTest` (source-scan guards) all git-confirmed diff-free; full suite green on SQLite AND PG17. *(Confirmation date = this change's archive-dir date once archived — not yet archived at authoring; do not stamp the authoring date.)*

**Applies to.** Any change that alters a status-FSM's shape — future Module S voucher-FSM transitions, Module A allocation-FSM activation, and further demand-side Profile evolutions.
