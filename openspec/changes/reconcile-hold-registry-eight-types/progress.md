# Progress — reconcile-hold-registry-eight-types

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **This change is verify-only (spec reconciliation, design L1).** Only task 1.1 runs the test suite; 1.2/2.1/3.1 are doc-writing (traceability table, fidelity-diff notes, Protected-file flag list) and 2.2 is `openspec validate`. **NO task writes production code, a new test, or a migration** — if one seems to, the premise is wrong (STOP).
- **Run the Hold suite via `php -d memory_limit=2G vendor/bin/pest --filter='…'`, NOT `php artisan test --filter`.** The artisan runner re-spawns a child that ignores `-d`, and this six-class Hold filter exhausts 128M during bootstrap (fatal at `HoldRegistryTest->setUp()`). The pest-direct form with the 2G flag runs the same 71 tests green in ~2s on SQLite. (Refines hot.md's "filtered runs fit 128M" — a *multi-class* filter does **not**.)
- **The eight-value contract's assertion points** (the source for task 1.2's scenario→test table): `HoldEnumsTest` (ordered 8-name `->value` map L27-36 + `toHaveCount(8)` L38 + `autoLiftable` partition L85-92) · `HoldRegistryTest` (all-8 manual-place dataset L103-112) · `HoldLifecycleTest` (operator-lift dataset incl. both finance-driven L189-195; auto-managed reject = `kyc`/`payment` only L215-218) · `ComplianceReadApiTest` (all-8 read-API dataset L171-180) · `CustomerAnonymisationHoldPrecedenceTest` (per-type matrix — only `compliance` blocks L85-94; enum-completeness tripwire L96-105) · `CustomerHoldsConsoleTest` (placeHold form options `=== HoldType::cases()` L134-135; "eight Hold types" L121).

---

## [2026-07-03 19:22] — 1.1 Regression-verify the shipped eight-value Hold contract
- **What was implemented:** verify-only. Ran the six-class Hold suite and confirmed every assertion task 1.1 lists is already present at the exact points the deltas assert — none absent, so **nothing added** (acceptance + design D4/L1). ZERO production code, ZERO new test, ZERO migration.
- **Files changed:** `tasks.md` (1.1 → `[x]`), `progress.md`, `hot.md`, `log.md`. No PHP touched; Protected `CLAUDE.md`/`CONTEXT.md` (already modified pre-iteration — Giovanni's hand-off) left untouched and un-staged.
- **Quality loop: green** — `php -d memory_limit=2G vendor/bin/pest --filter='HoldEnums|HoldRegistry|HoldLifecycle|ComplianceReadApi|CustomerAnonymisationHoldPrecedence|CustomerHoldsConsole'` → **71 passed, 399 assertions, ~2.0s, SQLite**. format/type_check/lint N/A (zero PHP touched). PG17 cross-engine deferred to the close ritual (proposal Impact; `knowledge/testing/rules.md`).
- **Assertion inventory (all present verbatim — do not re-add):**
  - `HoldEnumsTest`: eight-name ordered `->value` map (`admin`…`storage_payment_failed`) L27-36; `HoldType::cases()->toHaveCount(8)` L38; `autoLiftable` partition (`kyc`/`payment` true; `admin`/`fraud`/`compliance`/`credit`/`chargeback_review`/`storage_payment_failed` false) L85-92.
  - `HoldRegistryTest`: manual `PlaceHold` dataset over all eight types incl. `chargeback_review` L110 + `storage_payment_failed` L111 (L88-112).
  - `HoldLifecycleTest`: "lifts every operator-liftable Hold type" dataset incl. `chargeback_review` L193 + `storage_payment_failed` L194 (L173-195); "rejects an operator-lift of an auto-managed Hold type" dataset = `kyc`/`payment` only (L197-218).
  - `ComplianceReadApiTest`: "reports each of the eight Hold types … as a not-clear scope" dataset over all eight (L156-180).
  - `CustomerAnonymisationHoldPrecedenceTest`: per-type precedence matrix — `compliance` BLOCKS, both finance-driven types (+ five others) PROCEED (L85-94); enum-completeness tripwire binds the matrix to `HoldType::cases()` (L96-105).
  - `CustomerHoldsConsoleTest`: "exposes the placeHold form with the eight Hold types" — `hold_type` options `array_keys === HoldType::cases()` value map (L121-146).
- **Learnings for future iterations:**
  - Memory: use the 2G pest form (Codebase Patterns above). `php artisan test --filter` on this selection is a 128M fatal, not a suite failure.
  - Task 1.2 (traceability table) can build directly on the assertion inventory above — every `#### Scenario:` in the two delta files maps to one of these datasets; none is expected uncovered (design D4).
---
