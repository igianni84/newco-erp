# Testing — Hypotheses (test when possible)

> A hypothesis carries `Confirmations: N/3`. Confirmed 3× (dated) → promote to `rules.md`. Contradicted → note the contradiction and demote.

## Prove a class is ABSENT by listing the directory at runtime — never `class_exists('<literal>')` (PHPStan max flags it impossible)

**Hypothesis.** A scope guard that asserts a class / Action / Event does **not** exist must not use `class_exists('App\…\Absent')` with a string literal — PHPStan max evaluates the literal and flags the call `function.impossibleType` ("always false", dead code). Instead list the directory at runtime — `glob(app_path('Modules/<M>/<Dir>/*.php')) ?: []` (the `?: []` is required: `glob` returns `list<string>|false` and `array_map` rejects `false`), or a `RecursiveIteratorIterator` — map to basenames, and assert the **set** (`expect($names)->not->toContain('Absent')`, `toEqualCanonicalizing([...])`). A `class_exists($var)` on a variable derived from the listing is fine (not a resolvable literal). Bonus: a directory scan also catches a renamed-but-still-present class that a fixed `class_exists` list would miss.

**Confirmations: 2/3** (need 1 more distinct change).
- 2026-06-15 `catalog-product-spine` — `CatalogNamingCascadeTest` proves absence of a forbidden naming pattern by a recursive walk of the Catalog subtree (`getBasename('.php')` → `array_filter` → `toBe([])`).
- 2026-06-16 `parties-producer-lifecycle` — `SupplyLifecycleChainTest` states the rule + the PHPStan reason explicitly; `glob(...) ?: []` scope-guards both `Actions/` (only the six supply transitions) and `Events/` (no demand-side event exists).
- *(Related ancestor, NOT counted: 2026-06-12 `foundations-modules-skeleton` `ModuleConformanceTest` scans the modules dir for set-equality — same directory-scan idiom, but its driver is "no stray entries", not the `class_exists`-is-impossible mechanism.)*

**Applies to.** Any test that proves a class / Action / Event is absent (a category-neutrality or scope guard).
