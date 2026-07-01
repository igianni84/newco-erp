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
- **Active next:** **RM-09** (reconcile identity-auth ADR erasure overclaim) → **RM-10** (ClubCredit event rename `Issued`→`Accrued`) → **RM-24** (Product-Type immutability). All small, high-leverage.
- **Overall goal:** clear **Round 1** entirely + at least start **RM-01** (GDPR erasure) before Paolo.
- **Open incidental findings (§7):** **F1** DemoSeeder SQLite-only (PG-truncate) · **F2** prod operator-mgmt missing → SoD unsatisfiable in prod. Neither blocks the pre-Paolo demo; tracked so a later step picks them up.

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
| RM-09 | P2 honesty | Reconcile identity-auth ADR erasure overclaim | ADR consistency | **yes** | S | 🔴 |
| RM-10 | P2 canon | ClubCredit issuance event `Issued`→`Accrued` vocab | K J-16, EVT-16; DEC-018 | — | S | 🔴 |
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
| RM-24 | P1 canon | Product Type immutability guard | 0 BR-Identity-5 (new); DEC-023 | — | S | 🔴 |
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

#### RM-24 — Product Type immutability guard  ·  S  ·  🔴
- **Fixes:** 0 `BR-Identity-5` (new canon criterion); `DEC-023`.
- **Why:** canon adds "Product Type fixed at creation, never a type-edit." We likely satisfy it structurally (WINE-only, no Update path) — just add the explicit guard + test.
- **Done when:** attempted Product-Type edit rejected; test asserts it.

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

#### RM-09 — Reconcile identity-auth ADR erasure overclaim  ·  S · ADR ·  🔴
- **Fixes:** internal consistency. `decisions/2026-06-15-identity-auth.md:23,84` says GDPR erasure is "already built" — only the audit-redaction substrate is; the customer PII-erasure flow (J-9/9a) is not.
- **Scope:** correct/supersede the ADR wording (distinguish audit-redaction machinery from customer erasure); link RM-01. Do now (honesty) — the claim becomes true once RM-01 lands.

#### RM-10 — ClubCredit issuance event vocab `Issued`→`Accrued`  ·  S  ·  🔴
- **Fixes:** K `J-16`, `EVT-16`; `DEC-018`.
- **Why:** our vocab is `ClubCreditIssued`; canon renamed issuance to `ClubCreditAccrued` (Module-E-emitted; application events Module-S). Cheap alignment of names/seams now.
- **Done when:** rename across model/action/tests/consumed-event registry; `ClubCreditAccrued` is the seam name.

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
