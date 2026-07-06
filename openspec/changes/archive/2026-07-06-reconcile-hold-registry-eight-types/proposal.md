## Why

RM-04 ("Hold enum 6→8") is **already implemented, reviewed, approved and pushed** — commit `d8ec261` (2026-07-01): `HoldType` carries eight cases (`admin | kyc | payment | fraud | compliance | credit | chargeback_review | storage_payment_failed`), the `parties_holds` CHECK derives from `HoldType::cases()`, the operator console place-form offers all eight, and the full suite is green at eight (`HoldEnumsTest` pins the ordered eight-name map + `toHaveCount(8)` + the `autoLiftable` partition; `HoldRegistryTest` / `HoldLifecycleTest` / `ComplianceReadApiTest` / `CustomerAnonymisationHoldPrecedenceTest` / `CustomerHoldsConsoleTest` exercise all eight). The local adoption is the committed authority ADR `decisions/2026-07-01-adopt-dec-008-hold-types-8.md`.

But RM-04 was a Round-1 quick-win done as a **direct interactive commit outside the OpenSpec change flow**, so the **truth-specs were never synced** — and `openspec/specs/**` changes **only** via an archived change (invariant 11). Both spec-of-record capabilities still describe a **six-value** `HoldType` domain that now contradicts the shipped eight-value code and the live canon:

- `openspec/specs/party-registry/spec.md` — *Hold Registry* ("a `hold_type` from the six-value domain …", the "six Hold types" scenario, the `autoLiftable` false-list, "any of the six types"), *Hold Lifecycle and Lift Discipline* (operator-liftable = `admin/fraud/compliance/credit` only), and *Hold-Driven Status Coupling* (source note "six types").
- `openspec/specs/operator-console/spec.md` — *Operator places and lifts Customer Holds through the console* (place-form "six-value … every one of the six types"; the operator-liftable enumeration; source note "six types").

This is tracked as **F4** in `docs/validation/Remediation_Tracker.md` §7 ("truth-spec Hold Registry still 'six-value' vs code's 8 — RM-04 delta debt"). This change closes F4: it **adopts canon MVP-DEC-008 into the spec-of-record**. Canon authority: `c-mless/documentation` `MVP_Decisions_Register_v0.1.md:133` — **MVP-DEC-008** "Module K Hold-type enum completed to 8" (Q-AD-3 Option B, Paolo-ratified; `CHARGEBACK_REVIEW` (DEC-168) + `STORAGE_PAYMENT_FAILED` (DEC-160) are **eight first-class coordinate values**, the sub-type/cause-discriminator reading rejected), with `AC-K-FSM-10` / `AC-K-FSM-11` / `AC-K-EVT-18/19` aligned to eight. The change **adopts** the already-decided ADR; it does not re-litigate it.

## What Changes

This is a **spec-only reconciliation — zero production code, zero new behaviour, zero migration.** The eight-value behaviour shipped in `d8ec261` and is fully regression-covered; this change brings the two truth-specs (and, by hand-off, the Protected terminology docs) in line with it.

- **`party-registry` — MODIFY *Hold Registry*.** Six-value → eight-value domain; name the two finance-driven types and their canon/ADR provenance; the "eight Hold types" scenario; the `autoLiftable` false-list grows to the six operator-lift-only types; "any of the eight types"; the automatic-trigger clause names the two finance-driven Module-E signals; source note to canon MVP-DEC-008 + `AC-K-FSM-10` (eight) + `AC-K-EVT-18/19` + the adoption ADR. `autoLiftable()` stays `kyc`/`payment` only (unchanged).
- **`party-registry` — MODIFY *Hold Lifecycle and Lift Discipline*.** The operator-liftable set `admin/fraud/compliance/credit` grows to include `chargeback_review` + `storage_payment_failed` (both **operator-lift-only at launch** per the ADR — `chargeback_review` has no auto-lift signal; `storage_payment_failed` is manual-first, its `StoragePaymentSucceeded` per-cycle auto-lift a deferred Module-E seam); the operator-lift scenario; source note (`AC-K-FSM-11` "the other **six** operator-lift").
- **`party-registry` — MODIFY *Hold-Driven Status Coupling*.** Source-note-only: "six types" → "eight types (canon MVP-DEC-008)". **Zero normative change** — the coupling is count-independent (keys on coverage, not the type set).
- **`operator-console` — MODIFY *Operator places and lifts Customer Holds through the console*.** Place form six-value → eight-value + "every one of the eight types" (options derive from `HoldType::cases()`); the Lift paragraph's operator-liftable enumeration (`NOT autoLiftable()` = six types); the "no `Lift` on `kyc`" scenario names the six Lift-exposing rows; source note.

### Slice boundary — deliberately NOT in this change

| Deferred / excluded | Owner | Why not here |
|---|---|---|
| **No production code, no new test, no migration** | — | The eight-value enum + the CHECK-from-`cases()` + the full test suite + the console form already shipped in `d8ec261`; adding anything would be redundant (Simplicity First). This change is verify-then-reconcile-spec only. |
| **Module-E automatic-trigger consumers** — the `CustomerChargebackFlagged` / `StoragePaymentFailed` listeners that place the two finance-driven Holds, and the `StoragePaymentSucceeded` per-cycle auto-lift for `storage_payment_failed` | Module E (Build Workplan Phase 6) | Unwired seam per the ADR; the trigger-agnostic registry, the manual operator-placement path and the per-type classification/guards ship now (AC-K-MVP-2). Revisit the `storage_payment_failed` lift discipline (may flip to auto) only when Module E wires `StoragePaymentSucceeded`. |
| **Protected-file terminology edits** — `CONTEXT.md` **Hold type** glossary (still "exactly six values … not separate enum values") + `CLAUDE.md` l.67 ("6 types") | **Giovanni — human hand-edit** (GUIDE §3) | `CONTEXT.md` / `CLAUDE.md` are Protected. This change **flags** the exact lines (see Impact); it does not touch them. The `CONTEXT.md` **Hold type** entry is a *rewrite* (it actively contradicts DEC-008), not a one-word swap. |
| **Root `CLAUDE.md` Invariant #7 reword** ("Holds are never auto-lifted" — over-broad vs the per-type discipline) | standing item (ADR `2026-06-18-hold-lift-discipline-per-type`) | A separate, pre-existing recommendation; not part of the six→eight reconciliation. |

## Capabilities

### New Capabilities

_(none — no new capability; the change modifies two existing capabilities, `party-registry` and `operator-console`.)_

### Modified Capabilities

- `party-registry`: **MODIFY** *Hold Registry* (eight-value domain + the two finance-driven types + provenance), *Hold Lifecycle and Lift Discipline* (operator-liftable set grows 4 → 6; both finance-driven types operator-lift-only at launch), *Hold-Driven Status Coupling* (source-note only — count-independent).
- `operator-console`: **MODIFY** *Operator places and lifts Customer Holds through the console* (place-form eight-value + `HoldType::cases()`-derived; operator-liftable = `NOT autoLiftable()` = six types).

## Impact

- **No production code touched.** This is a reconciliation of the spec-of-record to code already shipped in `d8ec261`. On archive, four requirements across two truth-specs move to the eight-value domain.
- **Regression evidence (no new test needed).** The shipped suite already pins the eight-value contract at exactly the points the modified scenarios assert: `tests/Unit/Modules/Parties/Enums/HoldEnumsTest.php` (ordered eight-name map + `toHaveCount(8)` + `autoLiftable` partition — `kyc`/`payment` true, the other six false), `tests/Feature/Modules/Parties/HoldRegistryTest.php` (manual place, all eight), `HoldLifecycleTest.php` (operator-lift the two finance-driven types; auto-lift rejected only on `kyc`/`payment`), `ComplianceReadApiTest.php` (active-Hold-list reports all eight), `CustomerAnonymisationHoldPrecedenceTest.php` (the two finance-driven types proceed — only `compliance` blocks), `tests/Feature/Modules/OperatorPanel/Parties/CustomerHoldsConsoleTest.php` ("exposes the placeHold form with the eight Hold types").
- **Protected-file hand-off (for Giovanni — GUIDE §3, NOT touched by this change):**
  - `CLAUDE.md` **l.67** — Canonical Terminology: `account restriction (6 types)` → `(8 types)`.
  - `CONTEXT.md` **l.370–372** (**Hold type** glossary) — *rewrite*: eight first-class values; `chargeback_review`/`storage_payment_failed` **are** separate enum values (canon MVP-DEC-008); drop "map onto the `payment`/`fraud` families", "not separate enum values", and the "_Avoid_: … inventing a seventh type" line.
  - `CONTEXT.md` **l.215** ("six `HoldType`s") → eight; **l.222** (LiftHold list `admin/fraud/compliance/credit`) → +the two; **l.234** ("six base types" + "finance-driven subtypes → Module E" framing) → eight first-class types, the Module-E *triggers* (not the types) are the seam; **l.379–380** ("true for only four of six types") → six of eight; **l.367** (minor — "even the finance-triggered ones, when they land").
- **No ADR gate tripped, no new dependency.** The adoption ADR `2026-07-01-adopt-dec-008-hold-types-8.md` is the standing authority; this change consumes it into the spec-of-record.
- **Cross-engine.** No code change; the ralph verification runs the named suite green on SQLite (+ PostgreSQL 17 at the close ritual, per `knowledge/testing/rules.md`).
- **On archive.** F4 is closed; the truth-specs, the shipped code and canon MVP-DEC-008 agree on eight Hold types.
