## Why

Module K ships several **business-rule guards the frozen spec mandates but the code never enforced**, plus a cluster of **canon (c-mless) acceptance criteria added after our frozen `spec/` pin (MVP-DEC-007)**. Individually each is an S-size fix; batching them into one change amortizes the §2.7 close ritual + review over the whole set (cadence rule, `lessons.md` 2026-07-07) while staying one-task-per-iteration inside the loop. All items live in the **`party-registry` capability (Module K)** and touch no other module — a thematically coherent bundle. They close the Paolo-validation gaps the Module K verdict surfaced (RM-19/20/21/22) and land the canon-criteria batch (RM-23) that keeps us *current* rather than *wrong*.

## What Changes

Six guards + one signal, all inside `app/Modules/Parties/`:

- **RM-19 — Producer offboarding per-Profile cancellation (Profile-leg orchestration).** `RetireProducer` today cascades to Clubs only (`SunsetClub`); the **Profile leg is a deferred seam**. This change performs the orchestration: for every `Active`/`Lapsed` Profile under a sunsetting Club, drive `CancelProfile` with a **producer-initiated cancellation reason**, in **parent-before-child** order after the Club sunset (§10.2 / AC-K-J-19). **Faithful to zero-invention**: frozen §15.2 names **no `ProfileCancelled`** and §15.7 declares the exact signal-event shape a **deferred downstream-consumer concern**, so the per-Profile cancellation is **audit-only** (as `CancelProfile` already is), and the **subscribable Module-S signal event stays the §15.7 deferred seam** — no new event. §15.7's stated Module-K contribution is exactly "the producer-initiated transition logic + the cancellation reason at the originating boundary." Module K's role **ends at the per-Profile cancellation** — no Club-Credit conversion math (Module S, DEC-043 / AC-K-XM-23).
- **RM-20 — ProducerAgreement cross-shape scope mutual-exclusion.** Enforce **BR-K-Agreement-1 clause 2**: a Producer-wide (`club_id` NULL) and a per-Club (`club_id` set) agreement SHALL NOT both be `active` on the same Producer. `ActivateProducerAgreement` gains a **cross-shape guard** that **rejects** the activation when an `active` agreement of the *other* shape exists for that Producer (per AC-K-BR-Agreement-1 "assert rejection"). **BREAKING (spec correction):** this **inverts** the currently-shipped *ProducerAgreement Lifecycle* claim that the two shapes "MAY both be `active`" (the shipped "Scope isolation … MAY both be active" scenario is replaced). Same-scope supersession (`(producer_id, club_id)` tuple) is **unchanged**.
- **RM-21 — Club sunset/closed blocks new membership.** `CreateProfile` gains a **Club-status guard**: a Profile SHALL NOT be created against a Club that is not `active` (`sunset`/`closed` rejected), enforcing FSM-6 / BR-K-Club-3 ("sunset blocks new memberships"). Removes the "enforcement … is a downstream concern" deferral in *Club Lifecycle*.
- **RM-22 — `settlement_cadence` closed-set server enforcement (canon MVP-DEC-010 → mini-ADR).** `ProducerAgreement.settlement_cadence` moves from **free-text** to a **closed set of three** — `quarterly` (default), `monthly`, `semi-annual` — enforced **server-side** (domain + DB CHECK), not UI-only. **BREAKING (data):** the DemoSeeder's `annual` row is out-of-set and is migrated to a valid cadence.
- **RM-23 / Agreement-4 — new per-Club agreement requires an `active` Club (canon MVP-DEC-009 → mini-ADR).** Scoping a new ProducerAgreement to a specific Club requires that Club to be `active`; `sunset`/`closed` are not selectable. Producer-wide scope is ungated; **supersession inherits scope and is exempt** (a wind-down amendment on a since-`sunset` Club is unaffected).
- **RM-23 / Club-6 — `registration_flow` is an entry channel, never an approval bypass (canon MVP-DEC-022 → mini-ADR).** Collapse the redundant `invite_only` boolean into `registration_flow_type`; **no value auto-approves** (producer approval mandatory for every value); launch-live values `application_with_approval` (default) / `invitation_only` / `link_onboarding`; `open` carried latent, not selectable.
- **RM-23 / Identity-6 — hard 18+ age-gate at registration (canon MVP-DEC-022 → mini-ADR).** Block Customer registration when the self-attested `date_of_birth` implies an age below a **configurable platform minimum (default 18)**; **no Customer record is created**. Self-attestation only at launch (no document verification, BMD §2.8).
- **RM-23 / Profile-5 (K-side) — `auto_renew` inheritance (canon MVP-DEC-022 → mini-ADR).** A Profile's `auto_renew` **default-inherits the Club's `auto_renew` default** at creation and is operator-settable. The **customer self-toggle via the Consumer Portal** (BMD §2.4) is a **deferred frontend seam** (the Consumer Portal does not exist).
- **RM-23 / Producer-5 (interim minimal guard) — review-governed Producer content is locked while `active` (canon MVP-DEC-022 → mini-ADR).** A model-level chokepoint (the RM-24 pattern) **rejects any edit** of a review-governed field (`name`/`description`/`region`/`website`) on an `active` Producer with a localized "re-review required — deferred" reason. This codifies the **safety core** of BR-K-Producer-5 (unreviewed content never publishes) while the full **"edit re-arms Creator→Reviewer→Approver review" UX is deferred** to the future Producer-content-edit + review-FSM change (no edit-path / no Producer `reviewed` state exists today).

**Deliberately NOT in this change (slice boundary):**
- **RM-23 / J-15a — renewal grandfathering / attrition → RM-05.** All four J-15a clauses are **capacity-lineage** (canon MVP-DEC-011): (a) qty-not-below-cohort, (b) renewal admitted vs cap, (c) attrition + no-backfill vs WaitingList all need Module A's `qty` (AC-K-XM-18, an empty stub); (d) "no renewal auto-cancelled for capacity" is near-vacuous without a decrease path. The tracker already files J-15a under RM-05 (decision confirmed with Giovanni 2026-07-07).
- **RM-23 / Producer-5 full re-arm UX** — the Producer content-edit path + a Producer `reviewed` review sub-FSM (Creator→Reviewer→Approver on edit). Own change; the interim lock ships here.
- **RM-23 / Profile-5 customer self-toggle** — the Consumer-Portal `auto_renew` write (blocked frontend gate).
- **canon MVP-DEC-013** — a build-layer field-set ratification of the four Club config blobs that produces **no BR and no AC**, with its four spec-vs-agreement conflicts explicitly **deferred to the legal consistency review**. Nothing verifiable to land.
- **canon MVP-DEC-021 (CML-88)** — its acceptance artifacts are `AC-K-BR-Identity-1` (email-change workflow = **RM-25**, deferred-by-choice), `AC-K-J-9b` (data export = **RM-01**, already shipped), `AC-K-BR-Customer-3` (suspension review-flag), `AC-K-XM-25` — **none is one of RM-23's six named criteria**; folding it in would redo RM-01 and pull in the deferred RM-25.
- **Module S / E consumers of the RM-19 signal** (Club-Credit conversion), and **RM-22's Module-E/D settlement-timing reads** — downstream seams; this change ships only the K-side signal / the recorded cadence.

## Capabilities

### New Capabilities

_None. All requirements extend existing capabilities._

### Modified Capabilities

- `party-registry`:
  - *Producer Lifecycle* (MODIFIED) — RM-19: the Profile leg of the offboarding cascade is now performed (per-Profile cancellation with a producer-initiated reason; was a deferred seam).
  - *Profile Cancellation and Deactivation* (MODIFIED) — RM-19: the offboarding orchestration now drives the per-Profile cancellation with a producer-initiated reason (was "not the offboarding orchestration"); the subscribable Module-S signal event stays the §15.7 deferred seam (audit-only, no new event).
  - *ProducerAgreement Lifecycle* (MODIFIED) — RM-20: cross-shape mutual-exclusion at activation (inverts the "MAY both be active" scenario).
  - *Club Lifecycle* (MODIFIED) — RM-21: `sunset` (and `closed`) blocks new membership creation — enforced, not deferred.
  - *Profile — Multi-Profile Membership* (MODIFIED) — RM-21 (Club-active guard at creation) + RM-23/Profile-5 (`auto_renew` inheritance at creation).
  - *ProducerAgreement* (MODIFIED) — RM-22 (`settlement_cadence` closed set) + RM-23/Agreement-4 (per-Club scope requires an `active` Club).
  - *Club Registration Flow and Onboarding Channel* (ADDED) — RM-23/Club-6.
  - *Registration Age Gate* (ADDED) — RM-23/Identity-6.
  - *Producer Review-Governed Content Lock* (ADDED) — RM-23/Producer-5 (interim).
- `operator-console`:
  - *Operator creates a ProducerAgreement through the console* (MODIFIED) — RM-22 (settlement-cadence free-text → closed enum select) + RM-23/Agreement-4 (only `active` Clubs selectable for a per-Club scope).
  - *Operator creates a Profile through the console* (MODIFIED) — RM-21 (a `sunset`/`closed` Club is rejected).
  - *Operator creates a Club through the console* (MODIFIED) — RM-23/Club-6 (`registration_flow_type` select; the `invite_only` toggle is removed/subsumed).

## Impact

- **Code (`app/Modules/Parties/` only — module boundary invariant 10 preserved; no cross-module import beyond `Contracts`/`Events`/platform):**
  - RM-19: a `Producer→Profile` walk (query `Profile::whereIn('club_id', <producer club ids>)` — there is **no `Club→Profile` relation** today) wired into `RetireProducer`'s cascade, invoking the existing audit-only `CancelProfile` per `Active`/`Lapsed` Profile with a producer-initiated reason. **No new event, no migration** (the subscribable Module-S signal stays deferred, §15.7); the standalone operator `CancelProfile` is unchanged.
  - RM-20: a cross-shape guard + localized exception wired into `ActivateProducerAgreement`.
  - RM-21: a Club-status guard + localized exception in `CreateProfile`.
  - RM-22: a `SettlementCadence` enum + cast + migration CHECK; server-side validation in `CreateProducerAgreement`; console select.
  - RM-23/Agreement-4: a Club-active scoping guard in the ProducerAgreement creation path + console (only `active` Clubs selectable).
  - RM-23/Club-6: a `ClubRegistrationFlowType` value-set audit + the `invite_only` collapse + a no-auto-approve assertion; console.
  - RM-23/Identity-6: an age-gate in the Customer creation path + a configurable `min_registration_age` platform constant.
  - RM-23/Profile-5: an `auto_renew` column on `parties_profiles` + an `auto_renew_default` on `parties_clubs`; inherit-at-creation.
  - RM-23/Producer-5: a `Producer::booted()` `updating` guard locking review-governed fields while `active` + a localized exception (RM-24 pattern).
- **Migrations:** additive — `settlement_cadence` CHECK regen (RM-22), `parties_profiles.auto_renew` + `parties_clubs.auto_renew_default` (Profile-5), drop/subsume `parties_clubs.invite_only` (Club-6). Postgres-truthful, SQLite-compatible.
- **Docs / ADRs:** **3 mini-ADRs** in `decisions/` (+ INDEX) adopting canon **MVP-DEC-009** (Agreement-4), **MVP-DEC-010** (settlement closed set), **MVP-DEC-022** (Club-6 + Identity-6 + Profile-5 + Producer-5-interim) — each absent from the frozen `spec/`, sourced from **LIVE canon `cmless/main @ 360df0b`** (`lessons.md` 2026-07-02 + 2026-07-03).
- **Blast radius (build-time landmine — grep ALL callers, `lessons.md` 2026-07-06 / RM-08):** wiring guards into shared Actions (`CreateProfile`, `ActivateProducerAgreement`, `CreateProducerAgreement`, the Customer create path) can red existing tests/seeders/console `callAction` sites — the tasks name direct callers, but the migration blast radius is grepped from `app/ tests/ database/`, not trusted from a hand-list. Detailed in `design.md` Risks.
- **Traceability (deliberate gaps, listed above):** J-15a → RM-05; Producer-5 full re-arm UX → own change; Profile-5 self-toggle → Consumer Portal; MVP-DEC-013 (no artifact); MVP-DEC-021 (= RM-25 / RM-01 / others).
