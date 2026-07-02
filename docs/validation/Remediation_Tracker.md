# Compliance Remediation Tracker — Module 0 & K (pre-Paolo)

> **What this is.** The living backlog of fixes to bring our underwater build "more compliance-ready" before Paolo's module validation. Derived from the verdict reports ([`README.md`](./README.md) · [`Module_0_Verdict`](./Module_0_Verdict_v0.3-MVP.md) · [`Module_K_Verdict`](./Module_K_Verdict_v0.3-MVP.md)). This is the **source of truth for what remains** — not the reports (those are the snapshot; this is the todo).
>
> **How to use this (esp. from a fresh context window):**
> 1. Read **§1 Now / Next** (what's active) → **§2 Round plan** (the sequence) → **§3 At-a-glance** (full status table).
> 2. Pick the top actionable item; for anything **M/L**, spin an OpenSpec change (`/spec-to-change`) rather than free-coding — respect the repo's build discipline.
> 3. **When you finish an item:** set its status ✅ in §3 + the detail block, cite the evidence (tests/commit), append a dated line to **§6 Session Log**, and update **§1 Now / Next** + `hot.md`'s "Active Change" line so the next window knows.
> 4. **When you discover something incidental** (a gap, latent bug, or tech-debt that ISN'T the item you're on): append it to **§7 Incidental Findings** — never fix it silently, never drop it. It gets triaged into a round or an RM item when picked up.
>
> **Status legend:** 🔴 Not started · 🟡 In progress · ✅ Done · ⏸️ Blocked (needs another module) · 🔵 Deferred-by-choice
> **Size:** S (hours) · M (a session or two) · L (multi-session / own change)
> **Baseline:** local `spec/` @ MVP-DEC-007; canon @ DEC-023. Updated 2026-07-01.

---

## §1 Now / Next

- **Done & reviewed:** **RM-07** ✅ (Giovanni approved 2026-07-01, `5b64cc8`) — operators in the demo path; `reset()` event/audit-log truncation OK.
- **Done & reviewed:** **RM-04** ✅ (Giovanni approved 2026-07-01) — Hold enum 6→8 (`chargeback_review` + `storage_payment_failed`, both operator-lift-only) + mini-ADR adopting DEC-008. Consumers unwired (Module-E seam). Suite 1767/1767, PHPStan/Pint clean. **Committed + pushed.**
- **Done & reviewed & pushed:** **RM-09 + F3** ✅ (`5eb415d` + close-ritual `0e438fa`) — identity-auth ADR erasure overclaim corrected **in place**; the same overclaim in the substrate ADR (F3) folded in per Giovanni. Both ADRs now consistent on erasure state.
- **Done & reviewed & pushed:** **RM-10** ✅ (`04406b8`) — ClubCredit issuance event `ClubCreditIssued`→`ClubCreditAccrued` (canon DEC-018) + **mini-ADR** (reverses coherent frozen-spec DEC-166; §3 "—" upgraded, Giovanni-approved). EVENT seam-name only (no event class) → zero behaviour change; suite 1767/1767. App-event→Module-S + `MembershipFeePaid` (RM-03) noted as deferred seams.
- **Done & reviewed & pushed:** **RM-24** ✅ (`4c373af`) — Product-Type immutability guard (canon DEC-023 / BR-Identity-5) + **mini-ADR** (`2026-07-02-adopt-dec-023-product-type-immutable`; §3 "—" → "mini" — canon-DEC absent from frozen spec, same discipline as RM-04/RM-10, now the 3rd confirmation → lessons.md rule). TDD: model `updating` guard on `ProductMaster` (`isDirty('product_type')` → new `ProductTypeImmutable`), the only path-complete chokepoint (real mutable column + `$guarded=[]`, no update Action). Fires on UPDATE only (creation free), passes lifecycle transitions. Zero behaviour change — codifies an already-satisfied invariant. +2 tests; suite **1769/1769**, PHPStan/Pint clean.
- **Active next:** **RM-06** (PIM reject/edit review-freshness + explicit re-submit, S/M) — the last Round-1 item + Paolo's "rejection round" scenario. Then Round 2 = floor builds (RM-01 erasure / RM-02 enhanced-KYC).
- **Overall goal:** clear **Round 1** entirely + at least start **RM-01** (GDPR erasure) before Paolo.
- **Open incidental findings (§7):** **F1** DemoSeeder SQLite-only (PG-truncate) · **F2** prod operator-mgmt missing → SoD unsatisfiable in prod. (**F3** ✅ resolved 2026-07-02 — substrate-ADR erasure overclaim folded into RM-09.) Neither open finding blocks the pre-Paolo demo; tracked so a later step picks them up.

---

## §2 Round plan (Giovanni's "un paio di giri")

**Round 1 — quick wins: make it demoable + honest + cheap alignments** (all S, no module blockers)
`RM-07` seed operators · `RM-04` Hold 6→8 · `RM-09` reconcile erasure ADR · `RM-10` ClubCredit event rename · `RM-24` Product-Type immutability guard · `RM-06` reject/edit review-freshness (S/M — it's also Paolo's "rejection round" scenario).

**Round 2 — the compliance-floor builds + the canon design changes** (M/L, own OpenSpec changes)
`RM-01` GDPR erasure + Address (floor, headline) · `RM-02` enhanced-KYC threshold (floor) · `RM-03` membership charge-on-approval (DEC-016 — needs ADR) · `RM-05` capacity seat-set (decide K-side seam vs wait for Module A).

**Later / blocked** — everything in P3–P4 below: Module 0 completeness (bulk import, whitelist…) and Module K cross-module items that legitimately wait on Modules A/S/E. Kept in the backlog so nothing is silently dropped.

---

## §3 At-a-glance (full backlog)

| ID | Pri | Item | Fixes (AC / DEC) | ADR? | Size | Status |
|---|---|---|---|---|---|---|
| RM-01 | P0 floor | GDPR erasure / anonymisation + Address entity | K J-9, J-9a, FSM-16, BR-Customer-2; canon J-9b, DEC-015 | — | L | 🔴 |
| RM-02 | P0 floor | Enhanced-KYC €10k/€50k threshold + review-queue | K J-7a, EVT-12a | — | M | 🔴 |
| RM-03 | P1 canon | Membership charge-on-approval (collapse "approved-but-unpaid") | K J-16, J-1/2/3, EVT-15, FSM-2; DEC-016 | **yes** | L | 🔴 |
| RM-04 | P1 canon | Hold enum 6→8 (+ lift discipline for the 2 new) | K FSM-10/11, EVT-18/19, MVP-2; DEC-008 | mini | S | ✅ |
| RM-05 | P1 canon | Hero-Package capacity seat-set (Active+Suspended) + WaitingList | K J-13/14/15, XM-18/19, FSM-2; DEC-011/017 | **yes** | L | ⏸️ (Module A qty) |
| RM-06 | P1 canon | PIM reject/edit review-freshness + explicit re-submit | 0 J-7, BR-Lifecycle-6; DEC-019 | — | M | 🔴 |
| RM-07 | P2 demo | Seed ≥2 operators + chain DemoSeeder + scenario data | env #2 (SoD demo) | — | S | ✅ |
| RM-08 | P2 demo | Separation-of-duties on Parties approval (Producer + membership) | K J-10 | — | M | 🔴 |
| RM-09 | P2 honesty | Reconcile identity-auth ADR erasure overclaim | ADR consistency | in-place | S | ✅ |
| RM-10 | P2 canon | ClubCredit issuance event `Issued`→`Accrued` vocab | K J-16, EVT-16; DEC-018 | mini | S | ✅ |
| RM-11 | P3 mod0 | Bulk import (CSV/Excel, configurable depth) | 0 J-6, FSM-14, BR-BulkImport-1/2/3 | — | L | 🔵 |
| RM-12 | P3 mod0 | Layer-1 possible-case-configurations whitelist | 0 J-13, XM-11 | — | M | 🔴 |
| RM-13 | P3 mod0 | `EnrichmentDataUpdated` event + post-active enrichment edit | 0 EVT-8 | — | M | 🔴 |
| RM-14 | P3 mod0 | Re-versioning on identity edit | 0 BR-Audit-1 | — | M | 🔴 |
| RM-15 | P3 mod0 | Producer-existence validation at Product Master creation | 0 XM-2 | maybe | S | 🔴 |
| RM-16 | P4 modK | Customer-segment materialisation + events | K FSM-14, EVT-3/13 | — | M | 🔵 |
| RM-17 | P4 modK | Marketing-consent double opt-in FSM | K J-6, FSM-15 | — | M | 🔵 |
| RM-18 | P4 modK | Producer invitation flow + events | K J-3, EVT-11 | — | M | 🔵 |
| RM-19 | P4 modK | Producer offboarding per-Profile cancellation signal | K J-19, EVT-14, XM-23, EVT-20 | — | S | 🔴 |
| RM-20 | P4 modK | ProducerAgreement scope mutual-exclusion guard | K BR-Agreement-1 | — | S | 🔴 |
| RM-21 | P4 modK | Club "sunset blocks new membership" guard | K FSM-6, BR-Club-3 | — | S | 🔴 |
| RM-22 | P4 modK | `settlement_cadence` closed-set server enforcement | K BR-Agreement-2; DEC-010 | — | S | 🔴 |
| RM-23 | P4 canon | New canon criteria batch (Agreement-4, Club-6, Identity-6, Producer-5, Profile-5, J-15a) | DEC-009/013/021/022 | — | M | 🔴 |
| RM-24 | P1 canon | Product Type immutability guard | 0 BR-Identity-5 (new); DEC-023 | mini | S | ✅ |
| RM-25 | P4 modK | Email-change verification workflow | K BR-Identity-1 | — | M | 🔵 |

---

## §4 Action items — detail

### P0 — Compliance floor (the heart of "più compliance")

#### RM-01 — GDPR right-to-erasure / anonymisation + Address entity  ·  L  ·  🔴
- **Fixes:** K `J-9`, `J-9a`, `FSM-16`, `BR-Customer-2`; canon `J-9b` (data export) + `DEC-015` (only `compliance` blocks, over 8 Hold types).
- **Why:** legal floor (right-to-erasure + 10-yr retention). Today 100% absent — no anonymise action, no `anonymised_at`, **no Address entity at all**. Highest compliance-narrative value.
- **Scope:** Address entity + PII-overwrite (deterministic placeholders) preserving keyed transactional history; Hold-precedence (only `compliance` blocks) — depends on RM-04 for the full 8-type set; operator-triggered data export (J-9b). Own OpenSpec change.
- **Done when:** anonymise action + tests for PII severance, history preservation, per-Hold-type precedence, and export; `FSM-16` orthogonality test green.
- **Note:** completing this is also what makes RM-09 (the ADR overclaim) truthful.

#### RM-02 — Enhanced-KYC €10k/€50k threshold + review-queue  ·  M  ·  🔴
- **Fixes:** K `J-7a`, `EVT-12a`.
- **Why:** AML floor. Only `enhanced_kyc_flag`/`_at` seam columns exist; no detection workflow, no review-queue.
- **Scope:** single-tx €10k + cumulative-annual €50k detection (at-order-completion + periodic job), review-queue entry, `enhanced-kyc` review event; AML-threshold → lightweight re-screen (EVT-12a).
- **Done when:** both trigger paths tested (single + cumulative), review-queue entry asserted, trigger-source recorded.

### P1 — Canon divergences (Paolo's walkthrough scenarios — make us *current*, not *wrong*)

#### RM-03 — Membership charge-on-approval (collapse "approved-but-unpaid")  ·  L · needs ADR ·  🔴
- **Fixes:** K `J-16`, `J-1/2/3`, `EVT-15`, `FSM-2`; **`DEC-016`**.
- **Why:** we built the exact flow canon declared **wrong** — distinct `Approved` state + separate `ActivateProfile` gated on a (deferred) Module-E signal. Canon = producer approval `= charge = activation`, atomic; `MembershipFeePaid` from Module S; INV1, no INV0.
- **Scope decision (ADR first):** adopt DEC-016 locally; collapse the `approved`-but-unpaid intermediate; decide the launch seam for the charge trigger since **Module S/E don't exist yet** (options: a temporary K-internal atomic activate-on-approval that later delegates to the Module-S `MembershipFeePaid`, vs. wait). Write the ADR, then implement.
- **Done when:** ADR merged; no durable `approved`-unpaid state; approval atomically activates (charge-failure → not activated, no seat consumed); tests updated.
- **Depends on:** interacts with RM-05 (capacity enforced "at the atomic approve moment").

#### RM-04 — Hold enum 6→8 + lift discipline  ·  S · mini-ADR ·  ✅
- **Fixes:** K `FSM-10/11`, `EVT-18/19`, `MVP-2`; **`DEC-008`**.
- **Why:** enum is 6 (`toHaveCount(6)`); canon = 8 (+ `CHARGEBACK_REVIEW`, `STORAGE_PAYMENT_FAILED`). Cheap, unblocks RM-01's block-set + the chargeback/storage seams.
- **Scope:** add 2 enum cases + migration CHECK regen + per-type lift discipline (both manual-lift, finance-driven); consumers (`CustomerChargebackFlagged`, `StoragePaymentFailed`) stay unwired until Module E — note the seam.
- **Done when:** enum = 8, `toHaveCount(8)`, lift-discipline tests for the 2 new types; erasure block-set (RM-01) reads the 8-type set.
- **✅ Done (2026-07-01):**
  - **Mini-ADR** [`decisions/2026-07-01-adopt-dec-008-hold-types-8.md`](../../decisions/2026-07-01-adopt-dec-008-hold-types-8.md) (+ `decisions/INDEX.md`): local adoption of canon DEC-008. Records the spec self-contradiction (§4.8 says "six" but §4.8.1/§15.8 name the two finance-driven types — issue #2), the manual-lift decision for both new types (with the `storage_payment_failed` auto-lift-vs-manual-first reasoning — D4/`AC-AP-CON-FO-2`), and why no ALTER migration ships (the CHECK derives from `HoldType::cases()`; additive-only pre-prod, no PG env — §7 F1). Extends [[decisions/2026-06-18-hold-lift-discipline-per-type]] (operator-lift-only 4 → 6).
  - **Enum** `HoldType.php`: `+ChargebackReview ('chargeback_review')`, `+StoragePaymentFailed ('storage_payment_failed')` — appended last; `autoLiftable()` body unchanged (`Kyc || Payment`), so both new types fall through to operator-lift-only. Docblock updated 6→8 + the unwired Module-E consumer seam noted.
  - **Migration CHECK**: no code change needed — `parties_holds_hold_type_check` derives from `HoldType::cases()`, so a fresh migrate emits 8 tokens (docblock prose 6→8). SQLite (test engine) skips the CHECK; the enum cast carries the floor.
  - **Consumers unwired** (seam only, per scope): no `CustomerChargebackFlagged` / `StoragePaymentFailed` listener — deferred to Module E; the trigger-agnostic registry + manual `PlaceHold`/`LiftHold` paths ship correct for all 8 now.
  - **Evidence:** `HoldEnumsTest` (`toHaveCount(8)` + 8-map + autoLiftable truth-table +2 = false); **lift-discipline** `HoldLifecycleTest` "lifts every operator-liftable Hold type" provider extended 3 → 5 (the 2 new types lift via the operator path, NOT rejected as auto-managed); `HoldRegistryTest` (manual-placement provider 6 → 8), `ComplianceReadApiTest` (read-API not-clear provider 6 → 8), `CustomerHoldsConsoleTest` prose (placeHold form self-derives from `cases()` → 8). Docblocks updated in `Hold.php` + `ViewCustomer::holdTypeOptions()`. Suite **1767/1767** (+6), PHPStan 0, Pint clean.
  - **Downstream:** RM-01 (GDPR erasure) block-set now reads the 8-type set (DEC-015: only `compliance` blocks).

#### RM-05 — Hero-Package capacity seat-set + WaitingList  ·  L · needs ADR ·  ⏸️ (Module A)
- **Fixes:** K `J-13/14/15`, `XM-18/19`, `FSM-2` (`Applied→WaitingList`); **`DEC-011/017`**.
- **Why:** ships **UNCAPPED**; no-oversell-at-membership (a core invariant) unenforced. Canon: seat-set = `Active`+`Suspended`, enforced at the atomic approve moment; decrease-floor counts both; renewal grandfathering + attrition (J-15a).
- **Blocker / decision:** the capacity `qty` lives on Module A's allocation (DEC-020), and **Module A is an empty stub**. Decide: build the **K-side enforcement seam now** against a config/stub qty (so the invariant + WaitingList + seat-set are demoable and correct), consuming the real Module-A signal later — vs. wait for Module A. Recommend the seam (it's the K-owned invariant per DEC-020).
- **Done when:** capacity gate at approval (Active+Suspended), WaitingList placement + `WaitingListJoined`, Suspended→Active never re-checked, decrease-floor + grandfather tests.

#### RM-06 — PIM reject/edit review-freshness + explicit re-submit  ·  M  ·  🔴
- **Fixes:** 0 `J-7`, `BR-Lifecycle-6`; **`DEC-019`**. (Also Paolo's "rejection round" scenario.)
- **Why:** already **Partial vs our own baseline** (no re-submit path, no re-arm) and canon moved further: a pending rejection must **block** `reviewed→active`; re-submission is an explicit audited action; editing review-governed content **re-arms review**.
- **Scope:** explicit re-submit action (clears live flag, keeps history), block-gate on un-remediated rejection, review-freshness re-arm on identity/quality edits (observational edits don't gate). Ties to RM-14 (an edit path).
- **Done when:** 2-rejection-round test: approver blocked on flagged entity, re-submit restarts review, post-review edit re-arms, history preserved.

#### RM-24 — Product Type immutability guard  ·  S · mini-ADR ·  ✅
- **Fixes:** 0 `BR-Identity-5` (new canon criterion); `DEC-023`.
- **Why:** canon adds "Product Type fixed at creation, never a type-edit." We likely satisfy it structurally (WINE-only, no Update path) — just add the explicit guard + test.
- **Done when:** attempted Product-Type edit rejected; test asserts it.
- **✅ Done (2026-07-02, awaiting Giovanni review — TDD, mini-ADR):**
  - **Investigation first (no assumptions).** Confirmed: `product_type` is a real, mutable enum column on `catalog_product_masters` with `$guarded = []`; the sole writer is `CreateProductMaster` (creation only); `LifecycleTransition` writes only `lifecycle_state` (line 139); there is **no** update Action and `ProductMasterResource` is explicitly read-only ("deliberately NO Edit page … ships no update Action") — so immutability held *structurally* but rested on the *absence of a writer*, not an enforced invariant. Contrast Module K's `party_type`, immutable **by construction** (distinct per-subtype tables — [[decisions/2026-06-15-party-type-marker-on-subtype]]); a within-table discriminator must be **guarded**.
  - **Guard:** `ProductMaster::booted()` → `static::updating` throws the new `ProductTypeImmutable` when `isDirty('product_type')`. The **only path-complete chokepoint** (catches `update`/`save`/mass-assign — there's no Action-layer writer to guard). Fires on UPDATE only, so creation (runs through `creating`) sets the type freely; keys on `isDirty`, so a lifecycle transition (dirties only `lifecycle_state`) passes untouched — **empirically verified** (probe: lifecycle-only change ⇒ `isDirty('product_type') === false`). Localized reason (`lang/en/catalog.php` `immutable_product_type`, invariant 12).
  - **Mini-ADR** [`decisions/2026-07-02-adopt-dec-023-product-type-immutable.md`](../../decisions/2026-07-02-adopt-dec-023-product-type-immutable.md) (+ `decisions/INDEX.md`): §3 "ADR? —" upgraded to **mini** — DEC-023 is a canon criterion **absent from our frozen spec**, so its adoption is traced exactly as RM-04 (DEC-008) and RM-10 (DEC-018). Honest nuance in the body: unlike those, this is **zero-behaviour codification of an already-satisfied invariant**, not a divergence/reversal. This is the **3rd confirmation** of "canon-DEC adoption absent from spec → mini-ADR" → promoted to a `lessons.md` rule (2026-07-02).
  - **Scope discipline:** `product_type` only. `name`/identity re-versioning (BR-Audit-1) stays **RM-14**; not folded in.
  - **Evidence:** +2 tests in `tests/Feature/Modules/Catalog/ProductMasterTest.php` — (1) a type change is rejected (driven via `setRawAttributes` past the single-value enum cast — the only way to express a differing persisted value today; future-proofs the day a 2nd Product Type lands) and the persisted type is unchanged; (2) a same-type write **and** a real `SubmitProductMasterForReview` transition both pass (the no-false-positive / lifecycle-regression guard). Full suite **1769/1769** (+2), PHPStan 0, Pint clean.

### P2 — Demo-enablement + honesty

#### RM-07 — Seed ≥2 operators + chain DemoSeeder + scenario data  ·  S  ·  ✅
- **Fixes:** env ask #2 (SoD needs distinct actors; every walkthrough needs seeded data).
- **Why:** only 1 operator is seeded and there's no supported way to add a 2nd → the single operator can't approve its own entities. DemoSeeder isn't chained + seeds no operators.
- **Scope:** extend `OperatorSeeder`/`DemoSeeder` to create 2–3 operators with distinct emails (optionally distinct roles); seed a rejectable PIM entity + (post RM-05) a near-capacity Club + (post RM-01) an erasable Customer.
- **Done when:** `db:seed` yields ≥2 operators; the reject→distinct-actor-activate walkthrough runs in the console end-to-end. **Highest leverage — do first.**
- **✅ Done (2026-07-01):**
  - **New `OperatorDemoSeeder`** — 3 distinct role-segmented logins (`creator@` / `reviewer@` / `approver@ newco.test`, pw `password`), each granted its single authority-tier role on the `operator` guard; idempotent (`updateOrCreate` + `syncRoles`).
  - **`DemoSeeder` now self-provisions** the walkable env: chains `RoleSeeder` + `OperatorDemoSeeder`, **production-guarded** (aborts — it truncates business tables), and its `reset()` now also clears the event/audit log so the fixture's lineage is deterministic on re-run.
  - **Real-lineage SoD fixture** (`seedSodReviewScenario`): a `reviewed` Product Master ("Échézeaux Grand Cru", under active-projected DRC) built through the **real** `CreateProductMaster`→`SubmitProductMasterForReview` actions via `ActorContext::runAs`, so the creator/reviewer lineage is genuine — a direct-`create()`d row would let *any* actor activate and prove nothing. Creator/reviewer are used up → only the distinct `approver@` can activate; the creator is blocked (self-approval).
  - **Evidence:** `tests/Feature/OperatorDemoSeederTest.php` (3) + `tests/Feature/DemoSeederTest.php` (5, incl. **console** distinct-actor activate + rejection-with-notes, and the domain self-approval block). Suite **1761/1761**, PHPStan clean, Pint clean.
  - **Design decisions (reviewed & approved by Giovanni 2026-07-01):** (1) the ≥2 operators live in the **demo path** (`db:seed --class=DemoSeeder`), NOT the production bootstrap `DatabaseSeeder` (which stays the single env-driven operator) — per README §Recommendations "seed 2–3 operators + chain DemoSeeder". (2) `reset()` now truncates the event/audit log for a deterministic re-run — accepted, made safe by the production guard. (3) The near-capacity Club (RM-05) + erasable Customer (RM-01) scenario data are deferred to those items (blocked/unbuilt features), as the tracker scopes them "post RM-05 / post RM-01".

#### RM-08 — Separation-of-duties on Parties approval  ·  M  ·  🔴
- **Fixes:** K `J-10` (Producer onboarding); membership approval.
- **Why:** Catalog enforces SoD; **Parties does not** (Producer + membership approval are single-actor; System actor accepted). Parity + it's the compliance "four-eyes" property.
- **Done when:** distinct-actor guard on Producer activation (+ membership approval per policy); self-approval-blocked tests, mirroring Catalog.

#### RM-09 — Reconcile identity-auth ADR erasure overclaim  ·  S · in-place ·  ✅
- **Fixes:** internal consistency. `decisions/2026-06-15-identity-auth.md:23,84` says GDPR erasure is "already built" — only the audit-redaction substrate is; the customer PII-erasure flow (J-9/9a) is not.
- **Scope:** correct/supersede the ADR wording (distinguish audit-redaction machinery from customer erasure); link RM-01. Do now (honesty) — the claim becomes true once RM-01 lands.
- **✅ Done (2026-07-01, awaiting Giovanni review):**
  - **Chose in-place correction over a superseding ADR.** The *decision* (first-party Fortify+Sanctum, multi-guard, spatie RBAC, principal-references-party) is untouched — only two supporting clauses overstated build-state. Superseding would force restating the whole ADR + mark it `status: superseded`, **falsely signalling the auth architecture was replaced** — a worse honesty outcome than the overclaim. Repo rule "never edit substance, supersede" targets *decision* substance; precedent treats a factual contradiction-fix as a "minimal, faithful correction" (`decisions/2026-06-16-catalog-retirement-reference-integrity-scope.md:46`). `decisions/INDEX.md` therefore unchanged (not superseded).
  - **Edits to `decisions/2026-06-15-identity-auth.md`:** (1) a dated **Correction (2026-07-01 · RM-09)** banner after the frontmatter; (2) line ~23 ("First-party" bullet) and (3) line ~84 (Reasoning #1) reworded to distinguish the built erasure **seam** — PII-free `domain_events`, the `audit_records` `before`/`after` redaction path behind the structural-immutability trigger (`redactor` = documented PG-production principal), Customer PII confined to the Module K module table — from the not-yet-built customer PII-erasure **flow** (K J-9/9a: anonymise action, `anonymised_at`, PII overwrite-in-place, Address entity). Both corrected clauses now link **RM-01** as the item that makes the claim true.
  - **Verification (no code — doc-only):** grep-confirmed the flow is absent — no `redactor` in code (PG-production GRANT only), no anonymise/erasure action, no `anonymised_at`, no Address model; `app/Modules/Parties/Actions/CloseCustomer.php:31-33` explicitly documents `closed` ≠ anonymised (deferred `parties-anonymisation` seam). What *is* built: `database/migrations/2026_06_12_000002_create_audit_records_table.php` (nullable `before`/`after`) + `..._000004_add_immutability_triggers.php` (structural-immutability trigger leaving the redaction path open) + `..._2026_06_15_000005_create_parties_customers_table.php` (PII on the module table).
  - **Incidental F3 folded in (2026-07-02, Giovanni-approved):** the *same* overclaim in `decisions/2026-06-12-event-substrate-and-audit-store.md:54` ("erasure already works") — which RM-09's corrected text links to as the "seam" source — reworded with the same pattern + inline rectification marker. Both ADRs now consistent on erasure state. §7 F3 ✅.

#### RM-10 — ClubCredit issuance event vocab `Issued`→`Accrued`  ·  S · mini-ADR ·  ✅
- **Fixes:** K `J-16`, `EVT-16`; `DEC-018`.
- **Why:** our vocab is `ClubCreditIssued`; canon renamed issuance to `ClubCreditAccrued` (Module-E-emitted; application events Module-S). Cheap alignment of names/seams now.
- **Done when:** rename across model/action/tests/consumed-event registry; `ClubCreditAccrued` is the seam name.
- **✅ Done (2026-07-02, Giovanni-approved mini-ADR + rename):**
  - **⚠️ Contradicted our frozen spec (unlike RM-04).** `spec Module_K §15.8` + `AC-E-EVT-21` are internally *consistent* on `ClubCreditIssued` and annotate "(was `ClubCreditAccrued`)" per **DEC-166** — our snapshot had renamed Accrued→Issued. Canon **DEC-018** renamed it back (Issued→Accrued) + split application-event emission to Module S. Escalation-asymmetry again (canon DEC-008..023 absent from our snapshot); the reviewer re-verified this probe against canon source (Verdict line 153, 🟡; line 5).
  - **Mini-ADR** [`decisions/2026-07-02-adopt-dec-018-clubcredit-accrued.md`](../../decisions/2026-07-02-adopt-dec-018-clubcredit-accrued.md) (+ `decisions/INDEX.md`): the §3 "ADR? —" was upgraded to a **mini-ADR** (Giovanni-approved) — since we diverge from a *coherent* spec and invariant #11 bans editing `spec/`, the divergence needs a recorded trace. Mirrors [[decisions/2026-07-01-adopt-dec-008-hold-types-8]].
  - **Scope = EVENT seam name only.** No event class exists (all four ClubCredit events are Module-E seams Module K consumes + emits none) → the rename is docblocks + the `ClubCreditEventOwnershipTest` forbidden-name list, **zero behaviour change** (`ClubCreditAccrued` asserted ABSENT as a Parties event class, exactly as `ClubCreditIssued` was). 8 refs renamed across `ClubCredit.php`, `IssueClubCredit.php` (×2), `ClubCreditEventOwnershipTest` (×3), `ClubCreditIssuanceTest`, `SupplyLifecycleChainTest`. Module K's writer vocab (`IssueClubCredit`, "issuance", the test filename) left UNCHANGED — DEC-018 renamed the *event*, not the within-module Action.
  - **Deferred seams noted (not folded in):** per DEC-018 `ClubCreditApplied` is Module-S-emitted (re-home deferred — Module S is a stub); `MembershipFeePaid` ownership is **RM-03** (DEC-016). Docblocks reworded so neither is mis-stated as Module-E.
  - **Evidence:** touched-files run 13/13; full suite **1767/1767** (unchanged count — pure rename), PHPStan 0, Pint clean.

### P3 — Module 0 build-completeness (not compliance — lower for the Paolo push)

- **RM-11 — Bulk import** · L · 🔵 — J-6/FSM-14/BR-BulkImport-1/2/3. Largest scope hole, zero code. Not compliance-critical; deferred for the pre-Paolo push unless Paolo asks.
- **RM-12 — Layer-1 case-config whitelist** · M · 🔴 — J-13/XM-11. PIM's only breakability contribution; launch-testable.
- **RM-13 — `EnrichmentDataUpdated` + post-active enrichment edit** · M · 🔴 — EVT-8 (the 1 missing event of 22).
- **RM-14 — Re-versioning on identity edit** · M · 🔴 — BR-Audit-1. Needs an Update action/Edit page (also enables RM-06's edit path).
- **RM-15 — Producer-existence validation at creation** · S · 🔴 — XM-2. Or accept the boundary-law behaviour with an ADR note.

### P4 — Module K other (mostly wait on Modules A/S/E — kept for completeness)

- **RM-19 — Producer offboarding per-Profile signal** · S · 🔴 — J-19/EVT-14/XM-23/EVT-20. K-side seam, buildable now.
- **RM-20 — ProducerAgreement scope mutual-exclusion** · S · 🔴 — BR-Agreement-1 (code allows Producer-wide + Club-narrowed at once).
- **RM-21 — Club sunset blocks new membership** · S · 🔴 — FSM-6/BR-Club-3 (add club-status guard in `CreateProfile`).
- **RM-22 — settlement_cadence closed-set enforcement** · S · 🔴 — BR-Agreement-2/DEC-010.
- **RM-23 — New canon criteria batch** · M · 🔴 — Agreement-4, Club-6, Identity-6, Producer-5, Profile-5, J-15a (DEC-009/013/021/022).
- **RM-16 Customer-segment** · 🔵 · **RM-17 Marketing consent** · 🔵 · **RM-18 Producer invitation** · 🔵 · **RM-25 Email-change workflow** · 🔵 — deferred-by-choice (HubSpot/marketing/segment are downstream-heavy; revisit when their consumers exist).

---

## §5 Cross-refs
- Verdict reports: [`README.md`](./README.md), [`Module_0_Verdict_v0.3-MVP.md`](./Module_0_Verdict_v0.3-MVP.md), [`Module_K_Verdict_v0.3-MVP.md`](./Module_K_Verdict_v0.3-MVP.md).
- Canon delta detail: `scratchpad/delta-ask3.md` (session-local) + each report's Canon Overlay.
- Process context: team-memory `spec-divergence-from-cmless-documentation`.

## §6 Session Log (append one line per work session; newest last)
- **2026-07-01** — Tracker created from the Module 0 & K verdict reports. 25 items catalogued; Round 1/2 plan set. Nothing started yet. Next: RM-07.
- **2026-07-01** — **RM-07 ✅** (TDD). `OperatorDemoSeeder` (3 distinct role-segmented logins) + `DemoSeeder` self-provisions (chains Role+OperatorDemo), production-guarded, resets event/audit log, and stands up a real-lineage SoD fixture Master via the actual Catalog actions. 8 new tests (incl. console distinct-actor activate + rejection). Suite 1761/1761, PHPStan/Pint clean. Reviewed & approved by Giovanni; committed+pushed (`5b64cc8`). Next RM-04.
- **2026-07-01** — **RM-04 ✅** (TDD, mini-ADR). Adopted canon DEC-008: `HoldType` enum 6→8 (`chargeback_review` + `storage_payment_failed`, both operator-lift-only — the spec's own §4.8.1/§15.8 already named them; §4.8 "six" was the flagged self-contradiction). Mini-ADR `2026-07-01-adopt-dec-008-hold-types-8` + INDEX. `autoLiftable()` unchanged; migration CHECK derives from `cases()` (no ALTER — additive-only pre-prod). Consumers unwired (Module-E seam). Tests: `HoldEnumsTest` count/map/truth-table; **lift-discipline** `HoldLifecycleTest` provider 3→5; `HoldRegistryTest`/`ComplianceReadApiTest` 6→8; console prose. Suite 1767/1767 (+6), PHPStan 0, Pint clean. Reviewed & approved by Giovanni; committed + pushed. Next RM-09.
- **2026-07-01** — **RM-09 ✅** (doc/ADR, **in-place correction** — no code, no TDD). Corrected `decisions/2026-06-15-identity-auth.md` (top Correction banner + lines ~23 & ~84): the GDPR customer-erasure was overstated as "already built". Now distinguishes the built erasure **seam** (PII-free `domain_events`; `audit_records` `before`/`after` redaction path behind the structural-immutability trigger; Customer PII confined to the module table) from the not-yet-built customer PII-erasure **flow** (K J-9/9a: anonymise action, `anonymised_at`, PII overwrite, Address entity), linked to RM-01. Chose in-place over supersede — decision unchanged, so a supersede would falsely signal auth was re-decided; `decisions/INDEX.md` untouched. Grep-verified the flow is absent. **Incidental F3** (§7): same overclaim in `2026-06-12-event-substrate-and-audit-store.md:54` ("already works") — logged, recommend folding into RM-09. Awaiting Giovanni review. Next RM-10.
- **2026-07-02** — **F3 folded into RM-09** (Giovanni-approved: "adesso"). Reworded the same GDPR-erasure overclaim in the substrate ADR `decisions/2026-06-12-event-substrate-and-audit-store.md:54` ("already works" → "operates **in place**, overwrite not DELETE; the flow is not built, lands with RM-01") + inline rectification marker citing RM-09/F3. The identity-auth ADR and the substrate ADR are now consistent on erasure state; §7 F3 ✅. RM-09 review confirmed (in-place approved). Doc-only. Next **RM-10**.
- **2026-07-02** — **RM-24 ✅** (canon adoption; mini-ADR + TDD guard). Adopted DEC-023 / `BR-Identity-5`: Product Type fixed at creation, immutable thereafter. Investigation confirmed immutability held only *structurally* (real mutable column + `$guarded=[]`, no update Action, read-only Filament) — so added an explicit `ProductMaster::booted()` `updating` guard (`isDirty('product_type')` → new `ProductTypeImmutable`), the only path-complete chokepoint; fires on UPDATE only (creation free), passes lifecycle transitions (verified). Mini-ADR `2026-07-02-adopt-dec-023-product-type-immutable` + INDEX (§3 "—"→"mini" — canon-DEC absent from frozen spec; **3rd confirmation** of that discipline → promoted to a `lessons.md` rule). Zero behaviour change (codifies an already-satisfied invariant); scope = `product_type` only (name re-versioning stays RM-14). +2 tests; suite **1769/1769**, PHPStan/Pint clean. Awaiting review. Next **RM-06** (last Round-1 item).
- **2026-07-02** — **RM-10 ✅** (canon adoption; mini-ADR + rename). Adopted DEC-018: ClubCredit issuance event `ClubCreditIssued`→`ClubCreditAccrued`. Mini-ADR `2026-07-02-adopt-dec-018-clubcredit-accrued` + INDEX — records that this **reverses** our internally-consistent frozen spec DEC-166 (`Module_K §15.8`/`AC-E-EVT-21`), so unlike RM-04 it needed the ADR trace even though §3 said "—" (Giovanni approved the mini-ADR). EVENT seam-name only: no event class (all four ClubCredit events are Module-E seams K consumes + emits none) → docblocks + `ClubCreditEventOwnershipTest` forbidden-list, **zero behaviour delta**. K writer vocab (`IssueClubCredit`/issuance) unchanged. App-event→Module-S + `MembershipFeePaid` (RM-03) noted as deferred seams. Suite 1767/1767 (unchanged), PHPStan 0, Pint clean. Awaiting review. Next RM-24.

---

## §7 Incidental Findings (discovered mid-remediation — triage, don't drop)

> Things found **while doing** the RM items that aren't the item itself — gaps, latent bugs, tech-debt. Captured here so they're addressed in **some** step and never silently fixed or lost. Each is triaged into an RM item or a round when picked up. **Append F-numbers; newest last.** A finding graduates to an RM row (§3) once it's scheduled.
>
> **Severity:** 🟥 blocks a goal · 🟧 real, deferrable · 🟨 minor/tidy. **Status:** 🔴 open · 🟡 scheduled (→ RM/round) · ✅ resolved.

### F1 — DemoSeeder is SQLite-only (PG `TRUNCATE` rejects FK-referenced tables)  ·  🟧 · 🔴 · found 2026-07-01 (RM-07)
- **What:** `DemoSeeder::reset()` truncates FK-referenced tables one-by-one (masters, customers, and — new in RM-07 — `domain_events`/`audit_records`). PostgreSQL's `TRUNCATE` refuses a table referenced by a FK unless every referencer is truncated in the **same** statement or `CASCADE` is used; Laravel's PG `withoutForeignKeyConstraints` emits only `SET CONSTRAINTS ALL DEFERRED` (no effect on `TRUNCATE`). So `db:seed --class=DemoSeeder` would fail on Postgres (`domain_events` ← `event_deliveries.domain_event_id` + self-FK `causation_id`).
- **Pre-existing, not a regression:** true of the existing truncations too — RM-07 only extended the sweep. Verified by reading Laravel's `PostgresGrammar::compileDisableForeignKeyConstraints` (= `SET CONSTRAINTS ALL DEFERRED`), not empirically on PG.
- **Why it's fine today:** dev + demo run on SQLite (`withoutForeignKeyConstraints` = `PRAGMA foreign_keys=OFF`, which allows it); suite green. No PG demo/staging env exists yet.
- **Disposition:** convert `reset()` to one `TRUNCATE … CASCADE` (or `delete()`s) **when a Postgres demo/staging env is stood up** — ties to the hosting/infra open-stack gate (`CLAUDE.md`). Small; promote to an RM row then.

### F2 — Production operator management missing → SoD unsatisfiable in production  ·  🟥 (pre-go-live) · 🔴 · found 2026-07-01 (RM-07)
- **What:** the bootstrap `DatabaseSeeder`→`OperatorSeeder` seeds exactly ONE operator (from env). There is **no Filament operator resource and no artisan command** to add more (README env-readiness note, "no Filament user resource"). Catalog SoD keys on distinct actor **identity**, so a single-operator production instance can approve **nothing** — every activation is a self-approval, always blocked.
- **RM-07 scope:** fixed this for the **demo only** (`OperatorDemoSeeder`, 3 logins). Production is untouched by design.
- **Disposition:** production needs an operator-management surface (Filament operator resource + create/invite flow) before go-live — the production counterpart of RM-07. Own item (M). Not blocking the pre-Paolo demo; schedule as an RM row when the operator-admin surface is built.

### F3 — Same GDPR-erasure "already built" overclaim in the substrate ADR (not just identity-auth)  ·  🟨 · ✅ · found 2026-07-01, resolved 2026-07-02 (RM-09)
- **What:** `decisions/2026-06-12-event-substrate-and-audit-store.md:54` read "profile data lives in module tables where Module K's overwrite-in-place erasure **already works**" — the same overstatement RM-09 corrected in the identity-auth ADR. Only the erasure *seam* is built; the customer PII-erasure *flow* (K J-9/9a) is not (that's RM-01).
- **Why it mattered:** RM-09's corrected identity-auth text **links to this ADR** as the source of the "seam." A reader clicking through would still meet the "already works" claim RM-09 set out to remove — a residual contradiction between two ADRs.
- **✅ Resolved (2026-07-02, folded into RM-09 per Giovanni):** `:54` reworded with the same pattern — "operates **in place** (PII overwrite, never DELETE) … the erasure *flow* (K J-9/9a) is not yet built and lands with RM-01; this ADR builds only its *seam*" — plus an inline rectification marker citing RM-09/F3. The two ADRs are now consistent on erasure state.
