## Context

Separation-of-duties (SoD) — "each configured step performed by a distinct actor; self-approval never allowed" — is the one PRD-level approval discipline the operator surface owns (Admin Panel PRD §5.2). The frozen spec mandates it on the **3-step Creator → Reviewer → Approver** pattern, naming for Parties **"Module K §4.4 Producer activation"**; `AC-K-J-10` is the sole SoD-bearing acceptance criterion ("assert workflow with distinct actors at the configured depth"). The role-count is admin-configurable (Module K §0 Q3 / §4.4) but the distinct-actor **floor holds at any depth**, including the explicitly-blessed **2-step Creator → Approver**.

**Current state (verified against code + truth-spec):**
- **Catalog** enforces the floor for every commercial-impact activation via `app/Modules/Catalog/Lifecycle/ApprovalGovernance.php` (invoked inside the shared `LifecycleTransition`): `operatorPrincipalOrFail()` rejects a `system`/null actor; `assertSeparationOfDuties()` rejects `approver === creator` (and, at role_count 3, the reviewer). Creator = the first `domain_events` row's `actor_id`; reviewer = the latest `audit_records` `%.submitted` row.
- **Parties** has **zero** SoD. `app/Modules/Parties/Actions/ActivateProducer.php` gates only on from-state + KYC-cleared; the actor is read from `ActorContext` (default `System` in tests — `ProfileMembershipApprovalTest.php:77` asserts exactly that). The `operator-console` truth-spec states it verbatim: "the activate action SHALL present **no** 'second actor required' affordance — Producer activation is a single-operator, KYC-gated transition, **not** the catalog separation-of-duties governance."
- **Structural asymmetry:** the Producer FSM is linear (`draft → active → retired`) — there is **no `reviewed` state and no `.submitted` audit action**, so Catalog's *reviewer* source does not exist in Parties. Only the **creator** (the `ProducerCreated` actor) is persisted and recoverable.

Foundation decision: `decisions/2026-06-17-approval-separation-of-duties-role-gated.md` — RESOLVED: keep the SoD floor **as written — two distinct people; self-approval never allowed** ("vale il documento sempre"). No new ADR is needed; RM-08 implements that resolved floor for Producer activation. Scope confirmed with Giovanni (2026-07-06): **Producer only, 2-step depth**; membership SoD deferred.

## Goals / Non-Goals

**Goals:**
- Enforce a distinct-actor SoD floor on `ActivateProducer`: an authenticated `newco_ops` operator, distinct from the Producer's creator, alongside the unchanged KYC gate.
- Close the "System actor accepted" hole (reject `system`/null actors at activation).
- Reach parity with Catalog's floor at the spec-admissible 2-step depth, reusing the same substrate (`domain_events`, `ActorContext`) without violating module boundaries.
- Keep the P2 demo walkable: a Producer created by one operator, activatable by a distinct one.

**Non-Goals:**
- Membership / Profile approval SoD (`ApproveProfile`) — deferred (no AC backing; would need its own mini-ADR).
- A `reviewed` state / submit / reject / re-submit review FSM on Producer (the 3-step Reviewer leg).
- SoD on `ActivateProducerAgreement` / `ActivateCustomer` / `ActivateProfile`.
- Any admin-configurable distinct-actor toggle (the floor is non-configurable per ADR 2026-06-17).
- Extracting a shared platform SoD service (Parties gets its own guard).
- Solving F2 (production operator management) — RM-08 enforces the floor; production still needs the operator-admin surface to satisfy it (see Risks).

## Decisions

### D1 — 2-step Creator → Approver floor, not the full 3-step review FSM
The floor is **`approver ≠ creator`** on `ActivateProducer`, plus the operator-principal requirement (D4). This is the configured depth Module K §4.4 / §0 Q3 explicitly admit and the exact path Catalog's guard reduces to at `role_count < 3` (`assertSeparationOfDuties()` returns after the creator check). It satisfies `AC-K-J-10` at the 2-step depth.
- **Alternative (rejected):** add a `reviewed` state + submit/reject/re-submit to the Producer FSM for the full 3-step depth (mirroring Catalog's *Approval Governance* + review-freshness). Faithful to the 3-step reading of `AC-K-J-10` but size L, invents a review-governance FSM the launch spec does not require, and is out of RM-08's M scope. Recorded as a possible future change.

### D2 — A Parties-local SoD guard, not reuse of Catalog's `ApprovalGovernance`
Add a small Parties guard (e.g. `app/Modules/Parties/Governance/ProducerApprovalGovernance.php`) that replicates the operator-principal + distinct-actor logic. It reads **only platform-level collaborators** — `App\Platform\Events\DomainEvent` (the event store) and `App\Platform\Events\ActorContext` — never any `Catalog\*` class, honoring CLAUDE.md invariant 10 (no cross-module imports; events + contracts only).
- **Alternative (rejected):** import/reuse `Catalog\Lifecycle\ApprovalGovernance` — a cross-module import, forbidden. **Alternative (rejected):** promote the guard to `App\Platform` now — a refactor that would touch Catalog's shipped, tested guard for no launch benefit (over-engineering; the two guards differ on the reviewer leg). The deliberate, minimal duplication is documented; platform extraction is a future option if a third module needs it.

### D3 — Creator = the `ProducerCreated` actor, read from the platform event store
Recover the creator exactly as Catalog's `creatorOf()`: the earliest `domain_events` row for `(entity_type = 'Producer', entity_id)` by `id` (`orderBy('id')->value('actor_id')`). `CreateProducer` records `ProducerCreated` with `actorId: $this->actor->actorId()`, so the lineage exists whenever the Producer was created through the real Action. Apply Catalog's `normalizeActorId()` coercion so `===` holds across SQLite (int) and Postgres (numeric string).
- **Null-creator is vacuous:** a Producer with no recoverable creator actor (e.g. a system/seed-created row, or a `factory()->create()` that bypasses the Action) imposes no creator constraint — but the approver must still clear the operator-principal floor (D4). This mirrors Catalog and means the DemoSeeder must create the fixture Producer through the **real `CreateProducer` Action** for the SoD to be demonstrable (RM-07 lesson).

### D4 — Operator-principal floor: reject `system`/null actors
Activation requires `ActorContext::role() === ActorRole::NewcoOps` with a non-null `actorId()`; otherwise reject (`…requiresOperatorPrincipal`). A distinct-actor floor is meaningless without an authenticated principal, and this closes the verdict's "System actor accepted" hole. Direct mirror of `ApprovalGovernance::operatorPrincipalOrFail()`.

### D5 — No config knob; the floor is non-configurable
No `parties.approval.*` config is added. The resolved ADR 2026-06-17 keeps the floor fixed (no toggle); and role_count 3 is unreachable without a reviewer, so a `role_count` knob would be dead config. If a future change adds the reviewer leg, it can introduce the knob then.
- **Alternative (rejected):** mirror `config/catalog.php` `approval.role_count` for symmetry — rejected as dead configuration.

### D6 — Guard runs inside `ActivateProducer`, before any write, in the existing transaction
Within the `DB::transaction` and after the locked from-state re-read, evaluate in order: **from-state → operator-principal → distinct-actor → KYC-cleared → write + record `ProducerActivated`**. All guards throw before the `update`, so any rejection leaves `status` and the event log unchanged (from-state guard already re-reads under `lockForUpdate`). Placing the identity floor before the KYC gate means a self-approval is rejected on the SoD floor even when KYC is not cleared — a deterministic, documented order. `ProducerActivated` and its payload are unchanged.

### D7 — Console surfaces the affordance; the domain enforces
On `OperatorPanel/…/ProducerResource/Pages/ViewProducer.php`, the `activate` verb presents the **"second actor required"** affordance and surfaces a domain `…Violation` as a notification, leaving state unchanged — the same "surface, don't reimplement" contract Catalog uses (`decisions/2026-06-19-operator-console-read-binding-write-through-actions.md`). `ViewProducer` keeps its own page base (it deliberately does not extend the Catalog `OperatorConsoleViewRecord`); only the activate verb's affordance + exception-to-notification mapping are added.

### D8 — In-place honesty correction of ADR 2026-06-17 (no supersede)
Add a dated correction note distinguishing the SoD floor **built in Catalog** from Producer activation, which **shipped KYC-gated single-operator**; RM-08 makes the ADR's "already correct for Parties" claim true. Mirrors RM-09's in-place correction of the identity-auth ADR — the *decision* (retain strict SoD) is untouched, so no supersede and `decisions/INDEX.md` is unchanged.

## Risks / Trade-offs

- **[Existing System-actor call sites break]** Producer activation under the default `System` actor now fails the operator-principal floor. → Migrate `tests/Feature/Modules/Parties/ProducerLifecycleTest.php`, `tests/Feature/Modules/OperatorPanel/Parties/ProducerLifecycleConsoleTest.php`, and any DemoSeeder Producer activation to distinct operator principals via `ActorContext::runAs` (the exact RM-07 pattern). This migration is part of the change, not incidental.
- **[Demo proves nothing if the fixture bypasses the Action]** A `factory()->create()`d Producer has a null creator → any operator can activate. → The DemoSeeder Producer SoD fixture must be built through the real `CreateProducer` Action as operator A (leaving it activatable only by a distinct operator B), mirroring `seedSodReviewScenario` from RM-07.
- **[PG/SQLite actor_id type mismatch]** `domain_events.actor_id` is an uncast bigint. → Reuse Catalog's `normalizeActorId()` coercion so distinct-actor `===` comparisons hold on both engines; verify under the PG17 close-ritual run.
- **[F2 — production cannot satisfy the floor]** With a single seeded production operator, enforced Producer SoD makes activation impossible (self-approval always). → This is the **same** exposure Catalog already has; RM-08 extends it to Producer. It is **not a demo blocker** (RM-07 seeds 3 operators). Flag for go-live: production needs the operator-admin surface (§7 F2) before Producer onboarding runs live.
- **[Trade-off: 2-step, not 3-step]** RM-08 delivers `AC-K-J-10` at the 2-step depth only; the Reviewer leg is deferred. Accepted per Giovanni's scope decision; the spec admits the 2-step configured depth, so this is a faithful partial, not a divergence.

## Migration Plan

- **No database migration** — the creator is read from the existing `domain_events`; no schema change, no new event, no new dependency.
- Deploy is code-only; the behavior change (activation requires a distinct authenticated operator) takes effect immediately on deploy.
- **Rollback:** revert the change — no data migration to unwind.
- **Verification:** touched-file suite green, then the PG17 close-ritual full-suite + semantic-verify (GUIDE §2.7) since this is a code-bearing change.

## Open Questions

- None blocking. Forward flag: production go-live depends on the operator-admin surface (§7 F2) to make the enforced floor satisfiable with more than one operator — tracked, not in this change.
