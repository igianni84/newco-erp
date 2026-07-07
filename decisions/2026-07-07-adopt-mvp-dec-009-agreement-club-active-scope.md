---
type: decision
status: active
date: 2026-07-07
supersedes:
superseded-by:
---

## Decision: Adopt canon **MVP-DEC-009** locally — a **new** per-Club ProducerAgreement requires an **`active`** Club; Producer-wide scope is ungated; **supersession inherits scope and is exempt**

> ⚠️ **Number collision — read first.** This is canon **MVP-DEC-009** (`MVP_Decisions_Register_v0.1.md:134`, *"ProducerAgreement per-Club scope requires an `active` Club"*), **NOT** the greenfield **`DEC-009`** in our frozen spec (`spec/04-decisions/decisions.md:71`, *"Crurated as Discovery supplier"*, already superseded — `:139`). Unrelated. Use the full token **`MVP-DEC-009`** everywhere. Same trap the RM-03 ADR flagged for DEC-016 and the RM-06 ADR for DEC-019.

We adopt canon **BR-K-Agreement-4** (RM-23): scoping a **new** ProducerAgreement to a specific Club requires that Club to be **`active`**. A `sunset` or `closed` Club is **not selectable** — a create carrying such a `club_id` is **rejected** with a localized `ProducerAgreementClubNotActive`, no row and no `ProducerAgreementCreated` event.

**What changes (Module K / Parties):**
1. **Creation-time guard.** The check lives in the `CreateProducerAgreement` path (a per-Club scope with a non-`active` target Club → reject pre-write). `sunset`/`closed` → `ProducerAgreementClubNotActive`.
2. **Producer-wide scope (`club_id` NULL) is ungated.** A Producer-wide agreement references no Club, so there is nothing to gate.
3. **Supersession/renewal is exempt.** BR-K-Agreement-3 renewal **inherits** the superseded agreement's scope (it is not re-selected), so a wind-down amendment on a Club that has **since** become `sunset` is **admitted** — the guard fires only on a *fresh* per-Club scoping, never on scope inheritance.
4. **Distinct from historical settlement deref (BR-K-Club-2 / §6.2).** Reading an **already-locked** Club reference on an existing agreement for historical settlement is unaffected regardless of Club state; Agreement-4 gates only the *new-scoping* moment. The two must never be conflated (a settlement read against a `sunset` Club's locked agreement is fine; scoping a *new* agreement to that Club is not).

**Orthogonal to RM-20 (same change, same entity).** Agreement-4 is the **creation-time Club-active** guard; RM-20 / BR-K-Agreement-1 clause 2 is the **activation-time cross-shape mutual-exclusion** guard (`ProducerAgreementScopeConflict`). Different chokepoints, different exceptions, no overlap.

## Context: why this came up

- **RM-23 / Agreement-4**, one of the five named canon acceptance criteria in the Module K validation batch. `CreateProducerAgreement` today narrows to a Club (`club_id` optional, nullable = Producer-wide) with **no Club-status constraint** — a new agreement can be scoped to a `sunset`/`closed` Club.
- **This is an *erratum of omission*, not a canon reversal.** Canon states MVP-DEC-009 is the *"editorial completion of DEC-070"* — **"No new DEC"**. DEC-070 (the ProducerAgreement entity — Path Y, `spec/04-decisions/decisions.md:564`, **which our frozen `spec/` does carry**) admitted per-Club scoping with **no Club-lifecycle constraint**, carried verbatim into §4.6 + BR-K-Agreement-{1,2,3}, and the QA integration probe verified ProducerAgreement-lifecycle clean without raising it. So our frozen spec faithfully carries the *gap*, not a *wrong* rule — this is a completeness fix (add a missing guard), unlike the behaviour **inversions** of RM-20 or RM-06.
- **Escalation-asymmetry / grounding.** Canon added **BR-K-Agreement-4 + a §4.6 clause + AC-K-BR-Agreement-4** on **✅ 2026-06-18 (tech-team Q&A; Phase-E erratum)**. Our frozen `spec/` is pinned in `spec.lock` @ `4f48277` (`source_commit` = *"Module K PRD §9.5/§7.1 … screening launch posture"*), which stops at **MVP-DEC-007** — grep-confirmed: frozen `spec/` carries **BR-K-Agreement-1/2/3 only**, no Agreement-4. Sourced from **LIVE canon `cmless/main @ 360df0b`**, fetched read-only 2026-07-07 (`lessons.md` 2026-07-02 + 2026-07-03; the `git fetch cmless/main` grounding path, [[2026-06-17-spec-synced-from-documentation-repo]]).

## Alternatives considered

- **(A) Gate at activation (`ActivateProducerAgreement`) instead of creation. ❌ REJECTED (non-compliant + breaks the exemption).** Canon says the Club is *"not selectable"* and AC-K-BR-Agreement-4's negative path is *"attempt to **scope** a new agreement to a `sunset` Club … assert rejection"* — a **creation/scoping-time** contract. Worse, an activation-time gate would wrongly **block the canon-exempt supersession**: a renewal activates an agreement whose inherited-scope Club may have since become `sunset` (canon: **admitted**). Creation-time + supersession carve-out is the only reading that satisfies both the reject path and the exemption.
- **(B) Gate at both creation AND activation. ❌ REJECTED (over-enforcement).** Same failure as (A) on the supersession leg, plus redundant machinery. One chokepoint at the *fresh-scoping* moment is sufficient and canon-faithful.
- **(C) Gate at creation, exempt supersession by scope-inheritance. ✅ CHOSEN.** The guard fires only when a *new* per-Club `club_id` is being selected; supersession inherits the prior scope (never re-selects a Club) and so never re-enters the guard. Producer-wide (`NULL`) is structurally ungated. Matches AC-K-BR-Agreement-4's four paths verbatim (reject `sunset`, reject `closed`, admit `active`, admit since-`sunset` supersession).

## Reasoning: why C won

- **Spec fidelity is the yardstick.** AC-K-BR-Agreement-4 enumerates exactly four verification paths; only creation-time-with-exemption passes all four verbatim. The supersession-admitted path is the discriminator that rules out any activation-time gate.
- **Minimal, path-complete chokepoint.** `CreateProducerAgreement` is the sole writer of a new agreement's `club_id`; a guard there is the smallest correct surface (Simplicity First), mirroring the module's model-/action-layer guard idiom.
- **Low blast radius (grounding-confirmed).** Every existing `CreateProducerAgreement` caller (app/tests/seeder/factory) scopes to an `active` Club or is Producer-wide, so the new reject path breaks nothing existing — a new negative test is the only test-surface addition (contrast RM-20, which inverts shipped coexistence assertions).
- **Keeps the two Club-reference semantics separate.** Recording the BR-K-Club-2/§6.2 distinction inline prevents a future iteration from over-gating historical settlement reads on already-locked references.

## Trade-offs accepted

- **The supersession exemption needs an explicit signal.** Because the guard is creation-time, a renewal that re-creates a draft in a since-`sunset` scope must be recognized as scope-inheritance and skip the check; the exact mechanism (a renewal flag / a `supersedes` reference on the create) is the implementer's call (DEC-073) and is wired + tested in this change's tasks 3.2/3.3. Accepted — the ADR fixes the *contract* (exempt), not the representation.
- **Creation-time only; no approval-time re-check.** A draft created against an `active` Club whose Club later `sunset`s before activation is not re-gated at activation. Accepted as the faithful minimal reading (canon gates *selection*, not later drift); an activation-time refinement is a later item if the drift window ever matters — parallel to RM-21's creation-time reading (`ClubNotAcceptingMemberships`, [[2026-07-07-adopt-mvp-dec-022-club-membership-governance]]).
- **A new localized exception.** `ProducerAgreementClubNotActive` adds to the Parties exception set (EN + IT copy). Accepted — it is the module idiom and gives the console a surfaceable rejection.

## References

**Canon (authoritative — `c-mless/documentation` @ `cmless/main` `360df0b`, fetched read-only 2026-07-07; our `spec/` is frozen @ `4f48277`):**
- `MVP_Decisions_Register_v0.1.md:134` — **MVP-DEC-009** (full: new per-Club scope requires `active` Club; `sunset`/`closed` not selectable; Producer-wide ungated; supersession inherits scope + exempt; **erratum of omission — editorial completion of DEC-070, "No new DEC"**; distinct from BR-K-Club-2/§6.2; ✅ 2026-06-18 tech-team Q&A / Phase-E erratum; adds **BR-K-Agreement-4** + §4.6 clause + **AC-K-BR-Agreement-4**).
- `MVP_Decisions_Register_v0.1.md:132` — **MVP-DEC-007** (our frozen `spec/` pin stops here — the escalation-asymmetry; DEC-008..023, incl. this, post-date it).
- `Module_K_Acceptance_v0.3-MVP.md:195` — **AC-K-BR-Agreement-4** (the four verification paths: reject `sunset`, reject `closed`, admit `active`, admit since-`sunset` supersession — scope inherited, not re-selected).
- `Module_K_Acceptance_v0.3-MVP.md:192` — **AC-K-BR-Agreement-1** (scope shapes; the cross-shape mutual-exclusion clause = RM-20, an orthogonal activation-time guard in this same change).
- Module K PRD (canon) **§4.6 + §14.6** (new **BR-K-Agreement-4**); bridges **DEC-070** + the tech-team GitHub question (ProducerAgreement × Club lifecycle).

**Frozen spec (what we carry — the gap):**
- `spec/04-decisions/decisions.md:564` — **DEC-070** (ProducerAgreement = separate entity, Path Y) — present; its per-Club scoping is carried with **no Club-lifecycle constraint** (the omission MVP-DEC-009 completes).
- `spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md` — carries **BR-K-Agreement-1/2/3 only** (grep-confirmed; no Agreement-4). `spec.lock` `source_sha: 4f48277…` (MVP-DEC-007).
- ⚠️ `spec/04-decisions/decisions.md:71` — greenfield **DEC-009** (*Crurated as Discovery supplier*, superseded `:139`) — **unrelated** number collision (see banner).

**Local code (the guard surface):**
- `app/Modules/Parties/Actions/CreateProducerAgreement.php` — creation path; `club_id` nullable (`null` = Producer-wide, ungated). The Agreement-4 guard lands here (task 3.2).
- `app/Modules/Parties/Enums/ClubStatus.php` — `active` / `sunset` / `closed`.
- `app/Modules/Parties/Models/{ProducerAgreement,Club}.php`; new exception `App\Modules\Parties\Exceptions\ProducerAgreementClubNotActive` (task 2.4).
- Delta: `openspec/changes/parties-module-k-br-guards/specs/party-registry/spec.md` — *ProducerAgreement* (MODIFIED): the per-Club `active` requirement + Producer-wide-ungated + supersession-exempt clause and the "A per-Club agreement requires an active Club" scenario.

**Related ADRs:**
- **Sibling mini-ADRs authored in this same change (RM-22 + RM-23):** `2026-07-07-adopt-mvp-dec-010-settlement-cadence-closed-set` (settlement-cadence closed set — same ProducerAgreement entity) + `2026-07-07-adopt-mvp-dec-022-club-membership-governance` (Club-6 / Identity-6 / Profile-5 / Producer-5).
- **Same canon-adoption class** (each absent from the frozen spec, sourced from live `cmless/main`): [[2026-07-01-adopt-dec-008-hold-types-8]] · [[2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval]] · [[2026-07-02-adopt-dec-015-anonymisation-hold-block-set]] · [[2026-07-02-adopt-dec-019-review-freshness-resubmit]] · [[2026-07-02-adopt-dec-023-product-type-immutable]] · [[2026-07-02-adopt-dec-018-clubcredit-accrued]].
- [[2026-06-17-spec-synced-from-documentation-repo]] — why `spec/` is frozen + the read-only `git fetch cmless/main` grounding path.
