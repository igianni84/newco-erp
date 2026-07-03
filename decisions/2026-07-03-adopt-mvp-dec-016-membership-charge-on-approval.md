---
type: decision
status: active
date: 2026-07-03
supersedes:
superseded-by:
---

## Decision: Adopt canon **MVP-DEC-016** locally — membership is a single **atomic approve = charge = activation** action; there is **no durable "approved-but-unpaid" Profile state**

> ⚠️ **Number collision — read first.** This is **MVP-DEC-016** (`MVP_Decisions_Register_v0.1.md:141`, *"Membership payment flow corrected: charge-on-approval"*), **NOT** the greenfield `DEC-016` (*"AI/Operator Copilot kept at launch"*, `decisions.md:116`, superseded by DEC-021). They are unrelated. Code + docs use the full token **`MVP-DEC-016`** everywhere. Same trap the RM-06 ADR flagged for DEC-019.

We adopt the canon **charge-on-approval** membership flow (RM-03), collapsing the two-step `approve → (invoice) → pay-later → activate` flow our frozen spec carried — **the exact flow canon declared *wrong***.

**What changes (Module K / Parties):**
1. **Producer approval is the atomic approve-and-charge action.** `Applied → Approved → Active` happens **in one operation**; **`Approved` is a transient pass-through state, never a durable resting state**. A Customer is never left sitting in an unpaid `Approved`. (Canon keeps `Approved` in the FSM — it is retained, made non-durable — see Alternatives A vs B.)
2. **Charge-failure contract (canon-specified):** a charge that fails at approval → **not activated, no Hero-Package seat consumed, Profile stays `Applied`** (not a new/`Rejected` state), no `OriginatingClubLocked`; re-attemptable.
3. **`MembershipFeePaid` seam re-homed E → S:** **Module S emits**, **Module E records**, **Module K consumes** (canon DEC-173). The Hero Package fires **INV1 — there is no INV0** (DEC-157). It drives `Profile.fee_paid_at`, `ProfileActivated`/`ProfileRenewed`, and Club-Credit auto-generation.
4. **`OriginatingClubLocked`** fires on the Customer's first-ever successful approval into any Club (unchanged).

**Scope today vs. deferred (Module S/E are single-file stubs — no payment provider, no invoice entity, no `MembershipFeePaid` emitter):**
- **RM-03 delivers now:** the **state-shape collapse** (transient `Approved`, atomic `Applied → Approved → Active`), the **charge-fail contract**, the **seam re-home** (`MembershipFeePaid` E→S — docblock/seam-name only, no event class exists), and **INV1 (no INV0)** as the documented target. Since there is no charge infra, the charge is a **no-op seam** and `ApproveProfile` drives through to `Active` **synchronously** (a K-internal `ActivateProfile` call inside the approval transaction) — exactly the tracker's *"temporary K-internal atomic activate-on-approval that later delegates to the Module-S `MembershipFeePaid`."*
- **Deferred to Module S / E (F4–F6):** the real charge — the **charge-on-approval mandate captured at application** (save-card-plus-mandate, holds no funds, not a pre-auth), the **pull-capable instrument** (card one-step authorize+capture per DEC-101, or SEPA Direct Debit; **not** bank/wire transfer), the capture at approval, the `MembershipFeePaid` emitter, `Profile.fee_paid_at`, and the invoice entity. Mechanism (saved-card vs. mandate token) = dev's call per DEC-073. When Module S lands, the synchronous internal activate is replaced by the event-driven path (approval triggers the charge; `MembershipFeePaid` on capture drives `ActivateProfile`).
- **Deferred to RM-05 (blocked on Module A `qty`):** the Hero-Package **capacity seat gate** (seat-occupying set = `Active` + `Suspended`, enforced *at this atomic approve moment* — **MVP-DEC-017**). RM-03 **creates** the single atomic instant; RM-05 puts the seat gate on it. Until then, membership stays **UNCAPPED** at that moment.
- **Out of scope (separate items):** SoD/four-eyes on membership approval (RM-08); the six other MVP-DEC-022/CML-89 clarifications (18+ age-gate, `registration_flow` enum + no-auto-approve, waitlist manual conversion, `auto_renew` self-toggle, producer-content re-arm) — MVP-DEC-022 **reinforces** this decision (approval is *"atomic approve=charge=activation, mandatory for every onboarding channel, no value auto-approves"*) but its other rulings are their own remediation items.

## Context: why this came up

- **RM-03**, Round-2 P1-canon — and **Paolo's headline walkthrough scenario #1** (`docs/validation/README.md:34`: *"We built the flow canon declared **wrong**"*, 🔴 High). The frozen spec (`spec/` @ MVP-DEC-007) is **internally consistent on the wrong flow**: `ApproveProfile` (`applied → approved`, no event) + a **separate** `ActivateProfile` gated on a **Module-E** `MembershipFeePaid` for an **INV0** charge (`ActivateProfile.php:26-31`) — the "approved-but-unpaid" intermediate.
- Canon corrected it in **`MVP-DEC-016`** (register `:141`, ✅ **2026-06-21 tech-team Q&A**) — the resolution of the tech team's **GitHub question** on Hero-Package capacity, which surfaced the wrong membership flow. Canon commit `15b47a3` (*"Module K/S/E: charge-on-approval membership flow + Hero Package seat-occupying set (MVP-DEC-016/017)"*).
- **Escalation-asymmetry / grounding correction (the important process note):** MVP-DEC-008..023 are **absent from our frozen `spec/`** (pinned `spec.lock` @ `4f48277`, 2026-06-16); canon `main` is at `6f3c2f8` (**+23 commits**). This ADR is the **first RM grounded on *live canon*** — read via a read-only `git fetch cmless/main` in the sibling `../documentation` clone — rather than on the frozen spec + the validation Canon-Overlay's *summary*. That mattered: our internal lean was to **remove** the `Approved` state (see Alternatives), which the *actual* canon text (AC-K-FSM-2) forbids. → `lessons.md` 2026-07-03.

## Alternatives considered

- **(A) Remove `Approved` — single `Applied → Active` transition. ❌ REJECTED (non-compliant).** Our initial lean. Canon **keeps `Approved`**: Module K PRD §4.2.1 — *"the Profile **passes through `Approved` to `Active` in one operation**"*; **AC-K-FSM-2** enumerates `Approved → Active` as a transition to drive **and** asserts *"no durable `Approved` resting state."* Removing the enum case would **fail AC-K-FSM-2**. Canon's intent is "no *durable unpaid* `Approved`", not "no `Approved`."
- **(B) Keep `Approved` transient — atomic `Applied → Approved → Active`, `Approved` never durably rests. ✅ CHOSEN.** Literally compliant with AC-K-FSM-2 + PRD §4.2.1; **lower churn** than A (the enum + the `ActivateProfile` action survive — `ActivateProfile` becomes the internal `Approved→Active` step invoked by `ApproveProfile` now and by the Module-S `MembershipFeePaid` listener later); and it **is** the tracker's own described approach ("K-internal atomic activate-on-approval that later delegates to Module-S `MembershipFeePaid`").
- **(C) Defer the collapse until Module S can actually charge. ❌ REJECTED.** RM-03 is precisely one of Paolo's three walkthrough scenarios; deferring means **demoing the known-wrong two-step flow to Paolo**, defeating the remediation goal ("current, not wrong"). The shape-collapse is demoable now and independent of the (absent) payment infra.

## Reasoning: why B won

- **Spec fidelity is the whole point of RM-03.** The yardstick is Paolo's canon + acceptance criteria; AC-K-FSM-2 + PRD §4.2.1 dictate the transient-`Approved` shape. B is the only alternative that passes AC-K-FSM-2 verbatim.
- **The charge is genuinely Module S/E's** (mandate, instrument, capture, invoice) and doesn't exist yet — so a K-internal atomic activate-on-approval is the **honest bridge** that ships the correct *shape* now and delegates the *charge* later, with no dead payment code (Simplicity First / No-Laziness).
- **The seam re-home is docblock-only** (no `MembershipFeePaid` class exists — Module K only *consumes* it), exactly like the RM-10/DEC-018 rename: **zero behaviour change** for the seam-name part.
- **Canon nails the ambiguous branch** (charge-fail → stays `Applied`, no seat, no OC lock), removing what the validation overlay had left as inference — so the FSM contract is fully specified, not guessed.

## Trade-offs accepted

- **The "charge" is vacuous today.** With no payment infra, "approve = charge = activate" is effectively "approve = activate" until Module S lands. Accepted: canon's *intent* (no durable unpaid limbo) is satisfied now; the real charge is an honest forward seam, not faked.
- **A scaffold Module S will partly replace.** The synchronous in-transaction `ActivateProfile` call is temporary; Module S swaps it for the event-driven `MembershipFeePaid` path. Accepted — it keeps `Approved` transient (compliant) in the interim and the delegation point is documented.
- **`fee_paid_at` not added now** (no charge to stamp; free-club has no fee anyway) — forward with Module S.
- **Still UNCAPPED at the atomic moment** until RM-05 adds the seat gate (Module A `qty`). Accepted — MVP-DEC-017 capacity is a distinct blocked item; RM-03 only *creates* the instant it will gate.
- **Tests invert.** The two-step chain/console assertions (`MembershipActivationChainTest` "profileD stops at `Approved`", `ProfileMembershipChainTest` ordered approve-then-activate, the two operator verbs) flip to "approval yields `Active` in one operation"; `ActivateProfile`'s own unit guards (`Approved→Active`) largely stand (it survives as the internal step). Analogous to RM-06 inverting its "not terminal" test.

## References

**Canon (authoritative — `c-mless/documentation` @ `6f3c2f8`, fetched read-only 2026-07-03; our `spec/` is frozen @ `4f48277`):**
- `MVP_Decisions_Register_v0.1.md:141` — **MVP-DEC-016** (full decision: mandate-at-application, atomic approve=charge=activation, charge-fail contract, pull-capable instrument, `MembershipFeePaid` S-emits, INV1 no INV0).
- `MVP_Decisions_Register_v0.1.md:142` — **MVP-DEC-017** (seat-occupying set = `Active`+`Suspended` at the atomic moment → **RM-05**).
- `MVP_Decisions_Register_v0.1.md:147` — **MVP-DEC-022 / CML-89** (2): *approval atomic + mandatory for every channel, no auto-approve* (reinforces this decision).
- Module K PRD (canon) §4.2.1 (transient `Approved`), §7.1 step 7 (mandate at application), §13 / §13.1 (capacity at the atomic moment), §15.8 (`MembershipFeePaid` S-emits, INV1).
- Module K Acceptance (canon): **AC-K-FSM-2** (`:113` — transient `Approved`, charge-fail stays `Applied`), **AC-K-J-16** (`:96` — S-emits, INV1 no INV0, `fee_paid_at`, `→Active`), **AC-K-EVT-15** (`:274`), **AC-K-J-2** (`:78` — mandate captured at application), **AC-K-J-3** (`:79` — invitation pre-approves → atomic to `Active`).

**Local code (today's two-step, to be collapsed):**
- `app/Modules/Parties/Enums/ProfileState.php` (9 states incl. `Approved`); `app/Modules/Parties/Actions/{ApproveProfile,ActivateProfile,DeclineProfile}.php`; `ViewProfile.php:83-88` (two operator verbs); tests `ProfileActivationTest.php:92`, `MembershipActivationChainTest.php:150-151`, `ProfileMembershipChainTest.php`.

**Related ADRs:**
- [[2026-07-02-adopt-dec-018-clubcredit-accrued]] — explicitly left `MembershipFeePaid` E→S to **RM-03** (this ADR).
- [[2026-06-19-hold-status-coupling]] — `Suspended`/`Reactivate` coupling (seat set interaction, RM-05).
- [[2026-06-17-approval-separation-of-duties-role-gated]] — SoD floor (membership four-eyes = **RM-08**, separate).
- [[2026-06-17-spec-synced-from-documentation-repo]] — why `spec/` is frozen; the `git fetch cmless/main` grounding path.

**Same class as** the canon-adoption ADRs [[2026-07-01-adopt-dec-008-hold-types-8]] / [[2026-07-02-adopt-dec-015-anonymisation-hold-block-set]] / [[2026-07-02-adopt-dec-018-clubcredit-accrued]] / [[2026-07-02-adopt-dec-019-review-freshness-resubmit]] / [[2026-07-02-adopt-dec-023-product-type-immutable]] — but a **full ADR** (tracker §3 "ADR? **yes**"): a behavioural design change with a real seam decision, not a naming/guard tweak.
