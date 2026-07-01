---
type: decision
status: active
date: 2026-07-01
---

## Decision: adopt canon **DEC-008** locally — the `HoldType` enum is **eight** types, adding `chargeback_review` + `storage_payment_failed` (both operator-lift-only)

The unified `HoldType` domain grows from six to **eight**, adding the two finance-driven types Module K consumes from Module E:

- **`ChargebackReview` (`chargeback_review`)** — placed when `CustomerChargebackFlagged` arrives (Module E's automated Airwallex chargeback chain, D21 KEPT); Module K is the Hold registry-of-record on chargeback (spec §15.8). Resolved by an operator completing the dispute review (7-BD SLA — Admin Panel `AC-AP-CON-FO-6`); no auto-lift signal → **operator-lift-only**.
- **`StoragePaymentFailed` (`storage_payment_failed`)** — the per-cycle INV3 storage-payment Hold (spec §15.8). Manual-first at launch (D4 deferred): `AC-AP-CON-FO-2` has the **operator place it** (Stage 2) and, since the `StoragePaymentSucceeded` auto-lift consumer is a deferred Module-E seam, the operator also lifts it → **operator-lift-only** at launch.

Both new types therefore inherit the `admin`/`fraud`/`compliance`/`credit` lift discipline: `autoLiftable()` stays exactly `Kyc || Payment`, and the two new cases fall through to `false` (extends [[2026-06-18-hold-lift-discipline-per-type]] — auto-lift stays a two-type property, operator-lift-only grows 4 → 6). Their upstream event consumers (`CustomerChargebackFlagged`, `StoragePaymentFailed`) stay **unwired** until Module E — the same documented-seam posture the existing `payment`/`fraud`/`compliance`/`credit` placement triggers already have. The registry, the placement path (`PlaceHold`, trigger-agnostic per AC-K-MVP-2) and the lift path (`LiftHold`) ship correct for all eight now.

## Context: why this came up

Our frozen `spec/` **contradicts itself** on the Hold-type count: §4.8 (line 301) says "six types `admin/kyc/payment/fraud/compliance/credit`", but §4.8.1 (line 325, the N2 trigger-agnostic note) and §15.8 (lines 814–815, the consumed-events list) both **name `CHARGEBACK_REVIEW` and `STORAGE_PAYMENT_FAILED`**. We took §4.8 literally and shipped six (`HoldType.php` + `toHaveCount(6)`).

This is the exact inconsistency the tech team flagged upstream as **issue #2**, which Paolo resolved as **MVP-DEC-008 = 8 Hold types** — a canon correction (DEC-008..023) that never flowed back into our frozen snapshot (the escalation-asymmetry root cause — team-memory `spec-divergence-from-cmless-documentation`). The 2026-07-01 Module K validation code-confirmed the divergence (`docs/validation/Module_K_Verdict_v0.3-MVP.md` — Probe 1, Canon Overlay, gap `AC-K-EVT-18/19` + `AC-K-MVP-2`). Tracked as **RM-04** in `docs/validation/Remediation_Tracker.md`. This mini-ADR records the local adoption of DEC-008 before implementing.

## Alternatives considered

- **Stay at six.** Rejected — behind canon; leaves `AC-K-EVT-18/19` and `AC-K-MVP-2` unsatisfiable (the registry cannot record a type that does not exist) and blocks RM-01's erasure block-set, which reads the eight-type set (DEC-015: only `compliance` blocks anonymisation, evaluated over all eight).
- **Make `storage_payment_failed` auto-liftable** (because §15.8 says `StoragePaymentSucceeded` "lifts the Hold"). Rejected — that success signal is the **deferred Module-E automated path**; at launch the storage-payment trigger is **manual-first** (D4), so `AC-AP-CON-FO-2` has the operator both place and lift. Marking it auto-liftable now would make `LiftHold` reject the operator lift while no auto-lift consumer exists → a Hold no one can clear. Revisit if/when Module E wires `StoragePaymentSucceeded`.
- **Add a separate `ALTER TABLE … CHECK` migration** to widen the PG value-set. Rejected as unnecessary here: the `parties_holds` `hold_type` CHECK derives from `HoldType::cases()` at migration-run time, so a fresh migrate emits eight tokens automatically. The repo is additive-only pre-production with no PostgreSQL environment yet (`Remediation_Tracker` §7 F1); dev/test run on SQLite (CHECK skipped, the enum cast carries the floor). A widening ALTER migration is the right move only once a durable PG database exists — noted for that gate.

## Trade-offs accepted

- The two new consumers stay **unwired** until Module E — an unexercised seam, not dead code (mirrors the shipped `payment` auto-lift + non-`kyc` placement seams). The classification + guards are correct in advance.
- No value-set migration ships, so a **future PostgreSQL environment created from an already-applied older migration** would need a widening ALTER. None exists today; a fresh migrate is correct. Ties to the hosting/infra open-stack gate.
- `storage_payment_failed` is operator-lift-only **at launch**; if Module E later automates the `StoragePaymentSucceeded` lift, the discipline for that one type may flip to auto — a future, Module-E-gated revision of [[2026-06-18-hold-lift-discipline-per-type]].

## References

- Spec: `spec/02-prd/Module_K_PRD_v0.3-MVP.md` §4.8 (Hold entity — the "six" prose), §4.8.1 (DEC-160 lift discipline + N2 trigger-agnostic registry, names both finance-driven types), §15.8 (consumed events `CustomerChargebackFlagged`, `StoragePaymentFailed`/`StoragePaymentSucceeded`); `spec/03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md` `AC-AP-CON-FO-2` (operator places `STORAGE_PAYMENT_FAILED`, manual-first) + `AC-AP-CON-FO-6` (chargeback automated, operator surface = dispute evidence).
- Acceptance: `spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md` `AC-K-FSM-10` (Hold lifecycle), `AC-K-FSM-11` (lift discipline), `AC-K-EVT-18/19` (the two consumed events), `AC-K-MVP-2` (trigger-agnostic registry, every type placeable).
- Canon: **MVP-DEC-008** (c-mless/documentation register; issue #2) — Hold types 6 → 8; not present in our frozen register (stops at DEC-007).
- Validation: `docs/validation/Module_K_Verdict_v0.3-MVP.md` (Canon Overlay + Special Probe 1); `docs/validation/Remediation_Tracker.md` **RM-04**.
- Related: [[2026-06-18-hold-lift-discipline-per-type]] (extended — operator-lift-only 4 → 6); [[2026-06-12-production-db-engine]] (Postgres-truthful, SQLite-compatible migrations; the CHECK-from-`cases()` idiom); team-memory `spec-divergence-from-cmless-documentation`.
- Consumed by: RM-04 (`HoldType` enum + `HoldEnumsTest`/`HoldRegistryTest`/`HoldLifecycleTest`/`ComplianceReadApiTest` extended 6 → 8).
