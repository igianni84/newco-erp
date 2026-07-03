## Context

RM-03 adopts canon **MVP-DEC-016** ("membership payment flow corrected: charge-on-approval; producer approval = charge = activation, atomically"). The authority is the committed ADR `decisions/2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval.md` — **do not re-litigate its settled decisions here**. The frozen `spec/` (@ `4f48277`, MVP-DEC-007) is internally consistent on the flow canon declared _wrong_: `ApproveProfile` (`applied → approved`, durable) + a separate `ActivateProfile` gated on a Module-E `MembershipFeePaid` for an INV0 charge. Canon `main` is +23 commits ahead; the ADR is grounded on it (read-only `git fetch cmless/main`), verified against `MVP_Decisions_Register_v0.1.md:141`, `Module_K_Acceptance_v0.3-MVP.md` `AC-K-FSM-2` / `AC-K-J-2` / `AC-K-J-3` / `AC-K-J-16` / `AC-K-EVT-15`.

The **shape-collapse ships now**; the **charge is deferred** (Module S/E are single-file stubs — no mandate, no instrument, no invoice entity). This is Paolo walkthrough scenario #1: demoing the corrected shape is the whole point, and the shape is independent of the absent payment infra.

## Goals

- `ApproveProfile` drives `Applied → Approved → Active` in **one transaction** (transient `Approved`); `ProfileActivated` + conditional `OriginatingClubLocked` are the only events recorded.
- Charge-fail contract specified as the Module-S target (fail → stays `Applied`, no seat, no OC lock).
- `MembershipFeePaid` seam re-homed E → S in docblock + truth-spec prose (INV1, no INV0) — **no** event class touched.
- Operator console: the dead `activate` verb removed; the surviving **Approve** verb yields `Active`; i18n contract realigned.
- Every two-step test inverted; `ActivateProfile`'s isolated contract preserved.

## Non-Goals (deferred — do NOT build here)

- **The real charge** — mandate-at-application, pull-capable instrument (card authorize+capture / SEPA DD, **not** wire), capture-at-approval, the `MembershipFeePaid` emitter, `Profile.fee_paid_at`, the invoice entity → **Module S / E** (F4–F6). No dead payment code, no fabricated event class.
- **Hero-Package seat gate** (`Active` + `Suspended` at the atomic moment, MVP-DEC-017) → **RM-05** (blocked on Module A `qty`). Membership stays UNCAPPED at the atomic moment.
- **SoD / four-eyes on approval** → **RM-08**.
- The other six **MVP-DEC-022 / CML-89** clarifications (age-gate, `registration_flow` enum, waitlist conversion, `auto_renew` toggle, producer-content re-arm, Profile `role`) → their own items.

## Decisions (settled in the ADR — referenced, not re-opened)

- **Option B — keep `Approved` transient, do NOT remove the enum case.** `AC-K-FSM-2` enumerates `Approved → Active` and asserts "no durable `Approved` resting state"; removing the case would **fail acceptance**. `Approved` is a transient pass-through in one transaction.
- **`ActivateProfile` survives as the internal step.** `ApproveProfile` injects and invokes it inside the approval transaction (the K-internal atomic activate-on-approval); the same `ActivateProfile` is the future Module-S `MembershipFeePaid` listener's writer. Lower churn than folding it in, and its isolated contract (`ProfileActivationTest`) stands.
- **`MembershipFeePaid` re-home E → S is docblock/seam-name only** — no class exists, Module K only consumes; zero behaviour change (the RM-10 / DEC-018 precedent). **INV1, no INV0** (DEC-157).
- **Console `activate` verb removed; label stays "Approve".** "Approve" is the producer's L-PP action (the one retained producer write); activation is its automatic consequence. Success notification reworded to name the atomic outcome (interview Q2, Option A — subject to Giovanni's review before APPROVED).

## Risks & landmines (an autonomous agent must not step on these)

1. **Keep the `Approved` enum case** (`app/Modules/Parties/Enums/ProfileState.php`). Canon retains it, made non-durable. Do NOT remove it.
2. **No `MembershipFeePaid` event class.** Module K only *consumes* it; the re-home is docblock/prose only. Do NOT fabricate a class, a listener, or a Module-S/E event contract (zero-invention).
3. **`SupplyLifecycleChainTest` allow-list stays green UNAMENDED.** It globs `Actions/*.php`, filters non-`Create*`, and asserts exact-set equality; `ApproveProfile` / `ActivateProfile` / `DeclineProfile` are already listed, and this change **adds/renames/removes no Action or Event class**, so it does not trip. It also asserts `ProfileApproved` stays absent — still true (approval is audit-only). If a later refactor *does* add a non-`Create*` Action, register it in the same iteration (`lessons.md` 2026-06-23).
4. **`ComplianceIndependenceTest` OC-write guard.** It source-scans each Action and asserts `ApproveProfile` writes `'originating_club_id' =>` exactly once (non-null) and **every other Action zero**. The internal `ActivateProfile` call must **not** write `originating_club_id` (it does not today) — keep it that way, or this guard reds.
5. **The i18n contract test is `ProfileConsoleI18nTest`, NOT `CustomerConsoleI18nTest`** (the prompt's hint was off; verified). Removing `profile.actions.activate` / `profile.notifications.activated` from `operator_console.php` (en+it) **and** from `profileConsoleKitKeys()` must be atomic — a drop in one but not the other reds the EN-completeness / IT-distinct / sink-scan guards. Do **not** touch the six `customer.*` Account keys in that file (unrelated).
6. **The four precondition helpers double-drive approve→activate** (`ProfileCancellationTest:64-65`, `ProfileSuspensionTest:53-54`, `ProfileLapseGraceTest:65-66`, `MembershipSuspensionChainTest:150-153`). After the flip the second call hits an already-`Active` Profile and throws `cannotActivate` — **delete the `ActivateProfile` line** (approve alone reaches `Active`); do NOT wrap it in a try/catch.
7. **`MembershipActivationChainTest` is the big inversion.** Drop the explicit `ActivateProfile` (line 126); both approvals now reach `Active` (profileD is no longer left at `Approved`); `ProfileActivated` fires **twice**; the event multiset goes 7 → 8. Keep the OC-lock-once and Account-untouched assertions.
8. **`ActivateProfile` behaviour is UNCHANGED** (`Approved → Active`, records `ProfileActivated`, `cannotActivate` guard). `ProfileActivationTest` **stands unchanged** — do not rewrite it. Only its docblock re-homes the seam.
9. **`DeclineProfile` is untouched** (`Applied → Rejected`, terminal, event-silent). Do not modify it or its tests.
10. **Number collision:** this is **MVP-DEC-016** (membership), NOT greenfield `DEC-016` (AI/Operator Copilot, superseded by DEC-021). Always the full token `MVP-DEC-016` in code + docs.
11. **Green-between-tasks ordering.** After the `ApproveProfile` flip (its own task, which also inverts the approve-outcome observers), the console `activate` verb is dead-but-present and `ProfileActivationConsoleTest` (factory-forces `Approved`) stays **green** until the console-cleanup task removes the verb + that test. Keep each task green.
12. **Invariants untouched:** no money is handled (no charge), all recording stays transactional + PII-free (invariants 4/6/10 unaffected); the `Approved → Active` activation writes only `Profile.state` + `ProfileActivated`.
