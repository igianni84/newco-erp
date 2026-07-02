## Why

Our own truth spec (`product-catalog` → *Approval Governance*) and the frozen spec (`AC-0-J-7`, `BR-Lifecycle-6`, Module 0 PRD § 4.3) already state that a rejection keeps the entity in `reviewed`, the Creator edits in place and **re-submits**, and **the approval flow restarts from review** — with the full rejection history preserved. But the build is **Partial**: there is **no explicit re-submit operation**, and **nothing enforces that a pending rejection blocks activation**. Today a distinct approver can activate a just-rejected entity with no fresh review — `tests/Feature/Modules/Catalog/ProductMasterLifecycleTest.php:363-387` ("rejection is not terminal") asserts exactly this as correct behaviour. Canon **MVP-DEC-019** confirms and sharpens the same direction into an explicit **review-freshness invariant**. This is **RM-06**, the last Round-1 compliance-remediation item and Paolo's "rejection round" walkthrough scenario.

## What Changes

- **Add an explicit `re-submit` lifecycle operation** across all seven catalog spine entities — a `reviewed → reviewed`, **audit-only** governance decision (no domain event), the twin of the existing `reject`. It re-arms review by clearing the rejection-pending condition and records `catalog.<entity>.resubmitted` in the append-only trail.
- **BREAKING (behavioural): enforce a review-freshness block-gate.** `activate` (`reviewed → active`) SHALL be rejected while the entity's latest governance decision is an un-remediated rejection. This **inverts** the shipped "rejection is not terminal" behaviour (`ProductMasterLifecycleTest.php:363-387`), which is replaced by a "rejection blocks activation until re-submit" scenario.
- **Rejection-pending is derived from the append-only audit trail** (the latest governance action for the entity), consistent with `ApprovalGovernance`'s existing creator/reviewer derivation (design D5 — "the audit trail is the system of record; no per-entity governance columns"). **No schema flag, no migration.**
- **Operator console:** surface the `re-submit` verb on the catalog consoles (Paolo's walkthrough); the block-gate rejection surfaces for free via the kit's existing `surfaceLifecycleOutcome` path.
- **Adopt canon MVP-DEC-019 via a mini-ADR** (`decisions/2026-07-02-adopt-dec-019-review-freshness-resubmit.md`). The DEC is **absent from our frozen `spec/`** (which stops at MVP-DEC-007); per the standing rule (`lessons.md` 2026-07-02, confirmed 3× via RM-04/10/24) a canon-DEC adoption absent from the frozen spec always earns a mini-ADR, regardless of the tracker's advisory "ADR? —". ⚠️ Note: the frozen spec's *own* DEC-019 is an unrelated Module-S concern (club single-producer composites) — number collision, not the same decision.

### Slice boundary — deliberately NOT in this change

| Deferred concern | Future change | Why not here |
|---|---|---|
| **"Editing review-governed content re-arms review"** (the third MVP-DEC-019 leg — an identity/quality edit auto-re-arms review; observational edits don't gate) | **RM-14** (re-versioning on identity edit) | There is **no edit/Update path today** — no `Update*` action, the Filament resource is read-only (verified in RM-24). The auto-re-arm-on-edit leg has nothing to hook onto and would be dead code with no caller. RM-14 introduces the edit path and wires the re-arm into it. This change delivers the two legs buildable **without** an edit path: explicit re-submit + block-gate. |
| **Filament Edit page / operator edit surface** for catalog entities | **RM-14** | Same reason — the console stays read-bind + write-through-Actions; the only new verb this change surfaces is `re-submit`. |

## Capabilities

### New Capabilities

_(none)_

### Modified Capabilities

- `product-catalog`: the **Approval Governance** requirement — make the rejection→re-submit→re-review flow enforced (explicit `re-submit` operation + the activation block-gate on an un-remediated rejection, derived from the audit trail). The *Product Lifecycle State Machine* requirement is unchanged: `re-submit`, like `reject`, is a `reviewed → reviewed` governance decision that does not change `lifecycle_state`, so it belongs to Approval Governance, not the four state-changing transition operations.

## Impact

- **Domain / shared FSM:** `app/Modules/Catalog/Lifecycle/LifecycleTransition.php` (new `resubmit()`, twin of `reject()`); `app/Modules/Catalog/Lifecycle/ApprovalGovernance.php` (rejection-pending check in `guard()`, scoped to `Activate` alongside the SoD floor — derive-from-audit).
- **Actions (×7):** new thin `Resubmit{Entity}ForReview` for Product Master, Product Variant, Product Reference, Format, Case Configuration, Sellable SKU, Composite SKU. Required for all seven: the block-gate applies to all (shared mechanism), so every rejectable entity needs a way to clear rejection-pending — otherwise a rejected Format/Variant could never activate.
- **Operator console:** `app/Modules/OperatorPanel/Filament/**` — surface `re-submit`; the block-gate rejection reuses the kit's outcome-notification.
- **Tests:** invert `ProductMasterLifecycleTest.php:363-387`; add the 2-rejection-round scenario; extend `CatalogLifecycleChainTest`; console test for the new verb + the surfaced block.
- **Docs:** mini-ADR + `decisions/INDEX.md`; localized reason string(s).
- **No migration, no new dependency, no open-ADR gate** tripped (re-submit is audit-only like `submit` — no queued consumer).
