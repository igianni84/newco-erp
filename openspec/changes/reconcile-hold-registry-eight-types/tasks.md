# Tasks — reconcile-hold-registry-eight-types

> **Spec-only reconciliation. NO production code, NO new test, NO migration** — the eight-value behaviour shipped in `d8ec261` (RM-04). If any task appears to need a code change, STOP: the premise is wrong (design L1).

## 1. Regression-verify the shipped eight-value Hold contract

- [x] 1.1 Run the Hold suite and confirm it already encodes the eight-value contract the deltas assert. **No production code; no new test file.**
  - Command: `php artisan test --filter='HoldEnums|HoldRegistry|HoldLifecycle|ComplianceReadApi|CustomerAnonymisationHoldPrecedence|CustomerHoldsConsole'` — all green on SQLite.
  - Assert present (do not re-add): `tests/Unit/Modules/Parties/Enums/HoldEnumsTest.php` pins the ordered eight-name value map (`admin…storage_payment_failed`), `HoldType::cases()->toHaveCount(8)`, and the `autoLiftable` partition (`kyc`/`payment` true; `admin`/`fraud`/`compliance`/`credit`/`chargeback_review`/`storage_payment_failed` false).
  - Assert present: `HoldRegistryTest` (manual `PlaceHold` covers `chargeback_review` + `storage_payment_failed`), `HoldLifecycleTest` (operator-lift succeeds on the two finance-driven types; auto-lift rejected only on `kyc`/`payment`), `ComplianceReadApiTest` (active-Hold-list reports the two new types), `CustomerAnonymisationHoldPrecedenceTest` (the two finance-driven types PROCEED — only `compliance` blocks), `CustomerHoldsConsoleTest` ("exposes the placeHold form with the eight Hold types"; options = `HoldType::cases()`).
  - Acceptance: suite green; if any listed assertion is absent, add **only** the minimal assertion to that existing file (never a new test file) and note it in `progress.md`.

- [x] 1.2 Build the scenario→test traceability table in `progress.md`: every `#### Scenario:` in `specs/party-registry/spec.md` and `specs/operator-console/spec.md` maps to ≥1 assertion from 1.1.
  - Acceptance: table complete; zero unmapped scenarios (an unmapped scenario is the only trigger for a new assertion under 1.1).

## 2. Reconcile the spec-of-record (applied at archive)

- [x] 2.1 Fidelity check: diff each MODIFIED requirement in the two delta files against the live `openspec/specs/party-registry/spec.md` / `openspec/specs/operator-console/spec.md`; confirm the **only** semantic change is the eight-value reconciliation (six→eight; the two type names; the operator-liftable list `admin/fraud/compliance/credit` → +`chargeback_review`/`storage_payment_failed`; the DEC-008 / `AC-K-FSM-10` / `AC-K-FSM-11` / `AC-K-EVT-18/19` citations). Any other divergence = accidental edit → revert (design L2).
  - Acceptance: the diff is exactly the eight-value tokens; no normative change to *Hold-Driven Status Coupling* beyond its source note.

- [x] 2.2 `openspec validate reconcile-hold-registry-eight-types --strict` passes.
  - Acceptance: green (fix structure until it passes).

## 3. Protected-file terminology hand-off (human-owned — the loop does NOT edit)

- [ ] 3.1 Record (do **not** edit — `CONTEXT.md` / `CLAUDE.md` are Protected, GUIDE §3) the still-"six/6 types" lines in `progress.md` for Giovanni's hand-edit: `CLAUDE.md` l.67; `CONTEXT.md` **Hold type** glossary l.370–372 (rewrite), l.215, l.222, l.234, l.379–380, l.367.
  - Acceptance: the flag list is in `progress.md`; the change's diff touches no Protected file.
