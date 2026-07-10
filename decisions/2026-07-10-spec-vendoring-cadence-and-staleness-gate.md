---
type: decision
status: active
date: 2026-07-10
---

## Decision: `spec/` is the build authority and chases canon by deliberate refresh; staleness is measured, not assumed; a refresh is a code-free commit followed by a triage pass

Four rulings, in dependency order:

1. **`spec/` is the build authority, not a fixed yardstick.** It tracks `c-mless/documentation@handoff/` via `scripts/sync-spec.sh` and is refreshed deliberately. The "measure the drift" role it had been quietly playing moves to `spec.lock` + git history, where it always belonged: each verdict report already pins the baseline SHA it was written against, so it stays readable exactly as written.

2. **Staleness is measured by a fail-closed detector, and gates authoring.** A script compares `spec.lock:source_sha` against `cmless/main`, reports the distance in commits, and — **if it cannot reach the canon — exits non-zero rather than reporting a verdict.** It is to run as a `SessionStart` warning and as a **precondition of `/spec-to-change`**: no change is authored against a stale snapshot.
   > **Status: decided, NOT built.** No such script exists in `scripts/` as of this ADR. The shape was proven interactively during the grilling session (see Reasoning), not committed. Building it, wiring the hook, and amending the `/spec-to-change` skill are the first tasks of the follow-up change — as is the refresh itself. **This ADR authorises; it does not execute.**

3. **A refresh commit touches `spec/` and `spec.lock`. Nothing else.** No `app/`, no `tests/`, no `openspec/specs/**`. It is immediately followed by a **triage pass** that reads the diff and opens an RM row per surfaced divergence. *A refresh never changes behaviour — it changes what "correct" means.* Invariant: a refresh commit cannot turn a red test green or a green test red.

4. **This ADR decides the inbound direction only.** The outbound half of the escalation asymmetry — our findings entering the shared written record — is **named here as unresolved** and deferred to its own decision. It is an organisational choice, not a technical one.

**This ADR does not supersede [2026-06-17-spec-synced-from-documentation-repo](2026-06-17-spec-synced-from-documentation-repo.md). It extends it.** See Context.

## Context: why this came up

Incidental finding **F10** (`docs/validation/Remediation_Tracker.md` §7) observed that every P1-canon remediation item — RM-03, 04, 05, 06, 10, 22, 23, 24, and the newly-opened RM-26/27 — exists for one reason: a canon ruling is absent from our vendored snapshot, and we discover each one late, one mini-ADR at a time. F10 proposed raising the re-vendoring question to Giovanni, and framed it as needing **an ADR that supersedes the 2026-06-17 freeze**.

**F10's framing was wrong, and the error is the interesting part.** The 2026-06-17 ADR never froze anything. Its own text designs the refresh:

> *"The 'link' is **deliberate, not live**: `spec/` does not float to upstream `HEAD`. Refreshing is an explicit operation — run `scripts/sync-spec.sh`, review `git diff -- spec/`, commit the refresh + `spec.lock` as one commit."*

There was never a prohibition to lift. There was a **capability we never exercised**, and over three weeks the repo's own prose — this tracker, the verdict reports, the team memory — began calling the un-exercised capability a "freeze". The word created a constraint that no decision had ever imposed. *A vocabulary drift became a policy.*

Measured 2026-07-10, read-only:

| Fact | Value |
|---|---|
| `spec.lock` pin | `4f48277` (2026-06-16, pre-MVP-DEC-008) |
| Canon `cmless/main` | **`9eaa341`** (2026-07-10) = **MVP-DEC-036** |
| Distance | **35 commits**, 20 `handoff/` files |
| Diff size | +502 / −378 |
| `spec/` hand-edited? | **No** — byte-identical to `handoff@4f48277` (invariant 11 held) |
| `spec/` path citations that break on refresh | **0** — `sync-spec.sh` preserves the tree; this is the point of its design |

Two facts reframed the decision:

- **`spec/` was doing two incompatible jobs.** `docs/validation/README.md` says it plainly: *"Verdicts are against our **local frozen spec** … a **Canon Overlay** layers on where the current canon has moved."* One artefact cannot be both the authority you build from and the fixed metre-stick you measure drift against — not once the thing it mirrors starts moving. The whole RM series is the friction of that double duty.

- **The canon churn is not hypothetical, and it reaches built code.** MVP-DEC-031..036 landed in the 24 hours before this ADR. Module K PRD moved 162 lines and Module 0 PRD 108 — our two built modules, ~54% of the churn. And canon **reversed itself inside that window**: `57a2f72` (MVP-DEC-031) kept the `SellableSKU*` event family; `470068c` (MVP-DEC-033) renamed it to `IntrinsicSKU*` two commits later, superseding its own event-name half. *Verified against our code: `app/Modules/Catalog/Events/` ships `SellableSKUCreated/Activated/Retired` and zero `IntrinsicSKU*`.*

That reversal is the sharpest argument in this ADR's favour, and it argues **for** deliberate batching. A float — or an eager refresh on 2026-07-09 — would have had us relabel prose for MVP-DEC-031 and then rename events for MVP-DEC-033 the next day. Refreshing at `9eaa341` buys the settled answer and skips the intermediate. The 2026-06-17 ADR's *"refresh is a reviewed event, not a float"* was right, and this ADR keeps it.

**What the drift actually was.** Not that `spec/` sat still. That **nothing measured the gap**. Canon staleness was discovered by accident, during the grounding of an unrelated ADR (RM-05, 2026-07-09) — and that same grounding found `MVP-DEC-020`, seven DECs past the tracker's recorded baseline, which *settled an open question the tracker had left open and mis-framed*. The failure mode is a memory artefact asserting the state of a remote it never queried. **The same defect, in the same repo, on the same day:** `hot.md` asserted "11 commits ahead, not pushed"; it was true at 13:12 and false at 13:15, falsified by the very push it recorded as pending. The cure is identical in both cases — **ask the remote; never remember its state.**

## Alternatives considered

**On the role of `spec/`:**
- **Keep `spec/` frozen at `4f48277`; make a live-canon fetch a mandatory step of `/spec-to-change`.** F10's own "cheapest middle path". **Rejected on two counts.** (i) It protects nothing it claims to: the 122 `§section:line` anchors in this repo resolve against *canon*, quoted secondhand through ADRs — canon moves whether or not `spec/` does, so freezing `spec/` never pinned them. (ii) An authoring-time fetch is **module-scoped by construction**. Authoring a Module K change, nobody fetches Module 0 — and MVP-DEC-033 renames Module 0 events we have already shipped. The canon does not respect our module boundaries; a per-change fetch assumes it does.
- **Two artefacts: a mobile `spec/` plus an immobile `spec-baseline/`.** Explicit, no role ambiguity. **Rejected:** it duplicates ~7 MB of documents and merely relocates the question — *when does the baseline re-baseline?* — into a third ritual, i.e. a second place for staleness to hide. `spec.lock` + git history already are the baseline, for free.

**On the trigger:**
- **Wall-clock cadence** (e.g. weekly). Bounded staleness, predictable triage load. **Rejected:** arbitrary with respect to the work — it can move `spec/` under an in-flight change, and it stays silent at the one moment the answer matters (when you are about to author).
- **Refresh at module gates.** **Rejected:** it *is* the hole we have. MVP-DEC-033 (Module 0) landed while we worked Module K.
- **Float — refresh every session.** **Rejected:** destroys build reproducibility, and is the precise thing the 2026-06-17 ADR declined. The MVP-DEC-031→033 self-reversal above shows the concrete cost.

**On the shape of the refresh:**
- **Refresh + fixes in one change.** Less ceremony. **Rejected:** it puts +502/−378 lines of spec diff and a code diff in one review, where no reviewer can separate *"canon changed its mind"* from *"we changed the code"* — and a bad fix drags the refresh back out with it.
- **Refresh + realign `openspec/specs/**` to match.** **Rejected, and it is a trap worth naming.** The truth specs describe what the system does **today**; `spec/` describes what it **should** do. Realigning them on refresh would launder never-built requirements into "done", and invariant 11 already reserves truth-spec edits to change-archival.

## Reasoning: why this option won

- **It treats the cause, not the symptom.** F10 asked *"should we re-vendor?"* — a policy question. The evidence says the policy was never the problem: refresh was always permitted. The problem is that **the distance from canon was invisible**, so nobody could tell that a decision needed making. A fail-closed detector converts an invisible drift into a number on the screen at `SessionStart`. Everything else in this ADR follows from that number existing.
- **Fail-closed is not decoration.** The first detector drafted for this ADR ran `git ls-remote` against the private canon URL without credentials, got an empty string back, compared `"" != "4f48277"`, and cheerfully reported **STALE**. It would have reported STALE while perfectly in sync. A gate that cannot reach its oracle must **refuse to answer**, not guess — *a vacuous gate is worse than no gate* (the RM-05 ADR's own rejection of a Null capacity adapter, and the same shape as F1's falsified prediction).
- **Refresh-before-authoring dissolves a discipline instead of replacing it.** Once `MVP-DEC-024` is inside `spec/`, RM-26 no longer needs a "canon-DEC absent from the frozen spec → mini-ADR" trace (`lessons.md` 2026-07-02): the decision is simply *in the spec*, cited like any other requirement. The discipline is **not retired — it is re-scoped**, and now it has a precise, mechanical trigger it never had: it fires for canon decisions **newer than `spec.lock`**, and for nothing else.
- **A code-free refresh keeps the diff reviewable, and the diff is the deliverable.** +502/−378 lines is exactly the size at which a human can read a spec delta and *see* what changed — but only if no code is competing for attention in the same commit.
- **Nothing is lost historically.** Every verdict report already names the baseline it judged against (`docs/validation/README.md`: *"a `handoff/` snapshot at MVP-DEC-007"*). After the refresh those reports do not become wrong; they become **dated**, which is what a verdict is.

## Trade-offs accepted

- **The frozen-vs-canon delta framing that `docs/validation/` and the Remediation Tracker are built on collapses** the moment `spec/` catches up. Those documents become archaeology — accurate as of their pinned SHA, no longer a live diff. Accepted: they were snapshots by construction, and the tracker's §3 statuses (not its delta framing) are what the work actually consumes.
- **The refresh will surface divergences in shipped code**, and we know several in advance (see below). We are choosing to *learn them all at once, on our schedule*, rather than one accidental discovery at a time.
- **`sync-spec.sh` mutates Giovanni's `../documentation` clone** (`git checkout main` + `merge --ff-only`) — it is not read-only on his working tree. Accepted for now; the script already refuses to run against a dirty clone. Flagged so a future reader does not assume otherwise.
- **The `SessionStart` detector costs one network call per session** and will fail offline. It must degrade to a warning ("canon unreachable"), never to a false verdict.
- **Root `CLAUDE.md` still reads "`spec/` is the immutable v0.3-MVP handoff baseline."** That wording is what seeded the "frozen" misreading. `CLAUDE.md` is a **protected file**; this ADR does not edit it and instead **flags the wording for Giovanni's explicit approval**. ("Immutable" is true and should stay — it means *never hand-edited*; only `sync-spec.sh` writes it. It is "baseline" that reads as "fixed forever".)

## Known divergences this refresh will surface (triage input, not decisions)

Read from the canon diff before the refresh; each becomes an RM row in the triage pass, or is dismissed with a reason.

- **`MVP-DEC-033` — event-family rename.** `SellableSKU*` → `IntrinsicSKU*` (Module 0). We ship all three classes. ⚠️ Renaming an event type rewrites the `event_type` string in the **append-only, 10-year `domain_events` log** (invariant 4). Historical rows keep the old name. This is a genuine migration question, not a find-and-replace.
- **`MVP-DEC-034(a)` — capacity disposition.** `AC-K-XM-18/19` and `XM-20`'s capacity clause reconcile as **"Defer, not Fail"** until Module A integration. Our tracker records `XM-19` as **"NOT met"**. Note the vindication: the other build stores capacity as a `clubs` attribute — *precisely the K-owned design `MVP-DEC-020` declined and RM-05 refused* — and canon grants it only an **interim** pass. Our read-port with zero capacity storage is the design canon prefers.
- **`MVP-DEC-034(c)` — a real gap.** `AC-K-J-7a`'s scanner is time-boxed to before Module S go-live **plus a compensating manual control** (periodic Compliance review of trailing-12-month gross EUR). RM-02 shipped the scanner behind a `NullCustomerTransactionTotalsReader`; **the compensating control does not exist here.**
- **`MVP-DEC-034(b)` — not ours.** Account-scope Holds are FIX-NOW for the other build. Verified here: `HoldScope::Account` exists and `PlaceHold.php:127` cascades to `SuspendAccount`.
- **`MVP-DEC-035` — cross-module.** Reverses the categorical *"Module K never sends"*: the ERP owns transactional email behind one internal service. New Module K PRD **§14.9.1**; `BR-K-Contract-3` rewritten; ~40 loci / 11 files.
- **`MVP-DEC-024`** — already tracked as **RM-26** (+ RM-27). Canon names it in the **launch-blocking build set**.
- **`MVP-DEC-025 / 027 / 028 / 029 / 030 / 032 / 036`** — Modules 0 / A / B / D / S. Screen when those modules open (F8's standing instruction).

## Explicitly not decided here

**The outbound half of the escalation asymmetry.** Team memory records the root cause as bidirectional: *"we DO grill and DO find gaps … but we route via Giovanni↔Paolo calls + local errata, often resolved verbally and **archived UNSENT** — so our findings never enter the shared written record."* This ADR fixes only the inbound flow. The outbound flow stays open, and it is now sharper, not softer:

- The canon repo carries **18 issues, all from the TypeScript team, none from us** — *"two implementations, one spec"* (`hold-service.ts` in MVP-DEC-034; the report SHAs `31fcb55a` / `8bc9bddf` resolve in neither `documentation` nor here).
- `MVP-DEC-034` is Paolo ruling on **their** acceptance report. Canon is being shaped by an implementation that is not ours, and it is accommodating theirs.
- We hold at least one finding **verified as never asked** across all 18 issues: `WaitingListJoined` on birth-in-`WaitingList`. RM-05 also surfaced two defects *no canon document carries* (the oversell race; the `RenewProfile` naming trap).

Filing on Paolo's repo is an outward-facing action and is **Giovanni's to authorise**, in its own ADR.

## References

- **Extends** (does not supersede) [2026-06-17-spec-synced-from-documentation-repo](2026-06-17-spec-synced-from-documentation-repo.md) — the mechanism (`sync-spec.sh`, `spec.lock`, the `handoff/` → `spec/` mirror, "reviewed event, not a float"), and the ~360-citation blast-radius analysis that makes a path-stable refresh possible.
- `docs/validation/Remediation_Tracker.md` §7 **F10** (this decision's origin), **F8** (the un-triaged canon window → RM-26 / RM-27), **F1** (the falsified code-reading claim — why a gate must verify, not infer).
- `scripts/sync-spec.sh` · `spec.lock` (pin `4f48277`) · canon `cmless/main @ 9eaa341` = MVP-DEC-036, read-only fetch 2026-07-10; `../documentation` worktree verified clean before and after.
- `lessons.md` 2026-07-02 (canon-DEC absent from spec → mini-ADR — **re-scoped by this ADR** to "newer than `spec.lock`") and 2026-07-03 (ground on LIVE canon).
- Auto-memory `spec-divergence-from-cmless-documentation` (the escalation-asymmetry definition; *"two implementations, one spec"*). ⚠️ The tracker §5 cites it as **team memory**; it is personal auto-memory and is not in `.claude/memory/` — a fresh window on another machine cannot read it.
- Root `CLAUDE.md` → "Spec authority" (protected; wording flagged above, unedited).
- [2026-07-09-hero-package-capacity-seat-set-and-waitinglist](2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md) — the grounding session that discovered the drift, and whose zero-capacity-in-K design `MVP-DEC-034(a)` retroactively vindicates.
