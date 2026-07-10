## Context

Residuals of `parties-hero-package` (archived `2026-07-09-parties-hero-package`), surfaced by its GUIDE §2.7 semantic-verify. The authority for the *behaviour* is unchanged: ADR [`2026-07-09-hero-package-capacity-seat-set-and-waitinglist`](../../../decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md), decision **D8** (the gate table) and **D3** (the Club-row lock). **This change re-derives none of it.** It corrects one paragraph of prose that contradicts D8, and it pins three guarantees the suite leaves unasserted.

Two facts an implementer must hold:

1. **The code is right; the spec is wrong.** Do not "fix" `ApproveProfile` to match the requirement. `ApproveProfile.php:131` asserts the from-state before `:139` takes the Club lock, exactly as D8's table demands (its capacity column reads `—` for every state outside `{applied, waiting_list}`). The requirement's paragraph 2 was written from D3's *lock-then-count* rule and silently absorbed the from-state assert into the wrong clause.
2. **`openspec/specs/**` is never hand-edited** (CLAUDE.md invariant 11). The corrected prose reaches the truth spec only by archiving this change.

## Goals / Non-Goals

**Goals**

- Make the truth spec state the sequence the code implements, with the two guarantees the old prose dropped.
- Pin that sequence so it cannot silently regress.
- Pin `WaitingListJoined`'s root-ness, and the two console scenarios that exist as requirements but not as tests.

**Non-Goals**

- Any change to `app/`. If a task edits production code, the task is wrong.
- The RM-05 carve-outs (`AC-K-J-14` / `J-15` / `J-15a` / `XM-19`) — still blocked on Module A / Module 0 / Module S / the unmodelled period rollover.
- Tracker §7 **F12** (the `Profile ↔ Customer` lock-order inversion). Pre-existing, needs a *decision* before the producer HTTP surface lands, not a test.
- The remaining low-severity SUGGESTIONs (capacity port read twice per gated transition; `lapsed_at === null` defensive branch; birth-path grand-total event count).

## Decisions

### R1 — The corrected sequence is *guard → lock → count → read → gate*, and both dropped guarantees are stated

The requirement gains, in order: assert the from-state against the transaction-locked Profile re-read; **then** lock the `parties_clubs` row; count; read capacity; evaluate the gate. Two clauses the old prose lacked:

- a call from a state outside `{Applied, WaitingList}` is rejected **before any Club row is locked** — a doomed call must not serialise a Club against healthy concurrent approvals;
- such a call is **never** diverted onto the waitlist merely because its Club is at capacity. The at-capacity branch is reachable only from `Applied` (divert) and `WaitingList` (throw).

D3's *"the Club-row lock SHALL be acquired before the occupancy count"* paragraph stays verbatim — it was always true and is untouched. The two rules compose: the guard precedes the lock, and the lock precedes the count.

### R2 — The ordering is pinned **negatively**, by the statement the doomed call did not emit

A positive pin (*"an out-of-state approve reports `cannotApprove`"`*) is green under **both** orderings whenever the capacity gate would have rejected too, so it discriminates nothing. The discriminator is that a doomed call emits **no `parties_clubs` statement at all** — captured with `DB::listen`, asserted `toBeEmpty()`. That is also the operational claim worth having.

This is the exact shape `ProfileRenewalCapacityGateTest.php:185` already uses for `RenewProfile`'s grace sub-gate, and `ProfileUngatedTransitionsTest.php:200` for the non-gates. **Reuse the idiom; do not invent a second one.** Drive the dataset across the *later* gate's outcomes — at parity, free seat, and explicitly-uncapped (a `null` `by_club_id` override) — so independence from capacity is shown rather than coincidental.

Capacity `0` remains the cheapest at-parity fixture: `wouldOversell()` is true at every occupancy, so a Club admitting nobody is full while still empty and no members need seating.

### R3 — Root-ness is pinned at **both** `WaitingListJoined` entry points, not one

D7 fires the event at birth (`CreateProfile`) and at divert (`ApproveProfile`). They are two `record()` call sites, so one pin cannot cover the other. Assert `causation_id` is null **and** `correlation_id === event_id`, mirroring `ProfileActivationTest.php:74-75` verbatim.

`WaitingListJoinedEventTest` is a pure `payload()` unit test that never touches the recorder — root-ness cannot live there. It belongs in the two feature tests that already read the recorded envelope.

### R4 — The console pins assert the envelope and the toast, not the domain outcome again

The domain outcomes are already covered (`ProfileBirthStateRoutingTest`, `ProfileRenewalCapacityGateTest`). What the console tests must add is what only the console can break:

- **create-at-capacity**: the Profile is born `WaitingList` through the Filament create surface, and **both** events carry `actor_role: newco_ops` (the domain test records `System`). The form exposes no capacity field.
- **renew-at-capacity**: `renew` is visible from `lapsed`, the domain rejects, and `surfaceLifecycleOutcome` surfaces a **danger** notification whose body is the localized `parties.profile.club_at_capacity` reason. `renew` is the sole verb with domain sub-gates no visibility predicate can express, and this is its second UI-reachable rejection — the first (past-grace) is pinned at `ProfileLifecycleConsoleTest.php:116`.

Assert the notification **title and status** off `session('filament.notifications')`, not `assertNotified()` — the latter compares titles only and pulls destructively, so it can see neither `status` nor `body` (progress.md § Codebase Patterns).

### R5 — Every new pin is mutation-tested before its task is marked done

A pin that cannot fail is not a pin. For each: introduce the defect it claims to catch, run **that file only**, confirm red, restore, re-run.

| Pin | Mutant |
|---|---|
| Guard-before-lock ordering | Move `ApproveProfile`'s from-state `if` below `lockAndCountOccupiedSeats()` ⇒ the `parties_clubs`-statement assertion reds (and an `active` Profile lands on the waitlist) |
| `WaitingListJoined` root-ness ×2 | Pass `causationId: <some event_id>` into either `record()` call ⇒ only that entry point's pin reds |
| Console create-at-capacity | Hardcode `CreateProfile`'s birth state to `Applied` ⇒ reds |
| Console renew-at-capacity | Drop `RenewProfile`'s capacity gate ⇒ reds |

## Risks / Trade-offs

- **The tempting wrong fix is to "correct" the code to match the spec.** → The spec is the error. R1 states it, the ADR's D8 table governs, and `ProfileApprovalCapacityGateTest.php:213` already proves `active`/`lapsed` throw `cannotApprove` rather than divert.
- **A test-only change can still red the suite.** → `SurfacesDomainActionsOutcomeTest` and the console tests share fixtures; run the **full suite** on both engines (`progress.md`: 7 of 16 files drive the Action through `callAction('approve')`, invisible to `grep`). A `--filter` run is not proof.
- **A second Pest global helper of the same name is a fatal redeclare across the whole run**, not a shadow. The console files need their own at-parity fixture names.
- **This change does not make capacity compliant.** RM-05 still closes against a documented subset. Nothing here touches `AC-K-J-14` / `J-15` / `J-15a` / `XM-19`.

## Migration Plan

None. No schema, no config, no production code. Deployment is the archive.

## Open Questions

None blocking. Tracker §7 **F12** (the `Profile ↔ Customer` lock-order inversion) is recorded with three dispositions and is due before the producer-facing HTTP surface lands (DEC-083) — deliberately not resolved here.
