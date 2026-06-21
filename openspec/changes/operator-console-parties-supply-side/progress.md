# Progress — operator-console-parties-supply-side

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Operand-enum carve-out is LIVE (ADR 2026-06-21, task 1.1 landed).** OperatorPanel may now import each operated module's `Enums\*`. So the Club create (task 3.1) can `use App\Modules\Parties\Enums\ClubRegistrationFlowType;` and call `ClubRegistrationFlowType::from($selected)` in `createViaAction` — `ModuleBoundariesTest` admits it. Discipline (NOT mechanically guarded): import **operand** enums only (an Action `handle()` parameter you must construct — `ClubRegistrationFlowType`); **state** enums (`ClubStatus`, `ProducerAgreementStatus`) stay rendered via the model cast (`->value` + `instanceof BackedEnum`), never imported (D2). A state-enum import = dead code, caught in review.
- **`ModuleBoundariesTest` carve-out source of truth = `moduleBoundaryAllowedImports(Module)`** (the helper) + the guard test `it('scopes the operator-console Models/Actions/Enums carve-out to OperatorPanel only')`. The allow-list is now `{Models, Actions, Enums}` for `Module::OperatorPanel` only; lateral modules (Catalog) still get `{Contracts, Events}`. Widening it again needs a new ADR + updated guard assertions.

---

## [2026-06-21 09:27] — 1.1 widen the OperatorPanel carve-out to admit operand enums
- **What:** Implemented ADR `2026-06-21-operator-console-operand-enum-carveout.md` (task group 1, prerequisite of the Club create in group 3). In `tests/Architecture/ModuleBoundariesTest.php`, added `$other->namespace().'\\Enums'` to the OperatorPanel branch of `moduleBoundaryAllowedImports()` (now `{Models, Actions, Enums}`). Updated the carve-out guard test (`it('scopes the operator-console Models/Actions/Enums carve-out to OperatorPanel only')`): the per-module loop now asserts `Enums` is admitted for OperatorPanel, plus a new lateral-module assertion that `Parties\Enums` is **false** for Catalog; the whole-module `toBeFalse()` is retained. Rewrote the header docblock + the three inline carve-out comments to record the operand-enum admission (citing the ADR) and split operand (imported/constructed) from state (rendered via cast) — the old "render enum casts via their instances → stays exactly {Models, Actions}" sentence was falsified by an operand enum in a create Action. Fixed the now-wrong "(Lifecycle, Services, **Enums**, Exceptions, …)" internals example (Enums is no longer an internal for OperatorPanel).
- **Files changed:** `tests/Architecture/ModuleBoundariesTest.php` (+46/−25). Bundled the change-authoring artifacts this ADR produced: the ADR file itself (untracked), `decisions/INDEX.md` (ADR index entry), `CONTEXT.md` (Operand enum / State enum glossary terms — cited by the ADR's References).
- **Quality loop: green.** pint clean; `ModuleBoundariesTest` 3/3 (189 assertions); full SQLite suite **1206/1206** (6859 assertions, +9 from the new guard assertions); phpstan 0; pint --test clean; `openspec validate … --strict` valid; composer diff vs `main` empty; no protected-file edits.
- **Learnings for future iterations:**
  - The first privacy-law `it()` did NOT need touching for green: adding `Enums` to `->ignoring()` only broadens what's stripped, and no OperatorPanel code imports a module `Enums` yet (the real import lands in 3.1) — so it stayed green automatically. The operand-enum import is *admitted* now; it is *exercised* by task 3.1.
  - The helper + guard test are the only references to `moduleBoundaryAllowedImports` / the test-name string (grep-confirmed file-local), so the rename was safe.
  - Arch test is self-contained (declares its own helper) → safe to run by bare path, unlike the i18n tests that reuse `scanOperatorConsoleHardcodedSinks`.
---
