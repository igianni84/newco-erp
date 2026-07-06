> Discipline: one task per loop iteration; TDD (red → green → refactor); tests are never optional. Every task ends with the touched-file suite green + PHPStan max 0 + Pint clean. Mirror the Catalog SoD implementation (`app/Modules/Catalog/Lifecycle/ApprovalGovernance.php`) without importing any `Catalog\*` class (CLAUDE.md invariant 10).

## 1. Domain — the separation-of-duties guard (mirror Catalog, Parties-local)

- [x] 1.1 Add the localized copy keys to `lang/en/parties.php` **and** `lang/it/parties.php`: an `approval` group with `requires_operator_principal` and `creator_may_not_approve` (i18n invariant 12 — no hardcoded strings).
  - Assert (test `tests/Unit/…/PartiesApprovalCopyTest.php` or reuse an i18n test): both keys resolve non-empty in `en` and `it`. `vendor/bin/pint --test` clean.
- [x] 1.2 Add `app/Modules/Parties/Exceptions/SeparationOfDutiesViolation.php` (a domain exception) with static factories `requiresOperatorPrincipal(string $entity): self` and `creatorMayNotApprove(string $entity): self`, each resolving its localized message via the `parties.approval.*` keys.
  - Assert (`tests/Unit/Modules/Parties/Exceptions/SeparationOfDutiesViolationTest.php`): each factory produces the resolved localized message and carries the entity token. PHPStan/Pint clean.
- [x] 1.3 Add the guard `app/Modules/Parties/Governance/ProducerApprovalGovernance.php` (constructor injects `App\Platform\Events\ActorContext`) with `guard(string $entityType, int|string $entityId): void`, mirroring Catalog’s `ApprovalGovernance` minus the reviewer leg:
  - `operatorPrincipalOrFail()` → reject when `actor->role() !== ActorRole::NewcoOps` or `actor->actorId() === null` (`requiresOperatorPrincipal`); return the approver id.
  - `creatorOf()` → earliest `App\Platform\Events\DomainEvent` row for `(entity_type, entity_id)` by `id` (`orderBy('id')->value('actor_id')`), through a private `normalizeActorId()` copy (PG numeric-string vs SQLite int) so `===` holds on both engines.
  - if `creator !== null && approver === creator` → throw `creatorMayNotApprove`; a null creator is vacuous.
  - Imports only `App\Platform\*` + `App\Modules\Parties\*` — **no `Catalog\*` import** (verify against the module-boundary arch test if present).
  - Assert (`tests/Feature/Modules/Parties/ProducerApprovalGovernanceTest.php`, TDD — write first): system/null actor → `SeparationOfDutiesViolation` (`requiresOperatorPrincipal`); creator === approver → `creatorMayNotApprove`; creator ≠ approver → passes; null creator + operator approver → passes.

## 2. Wire the floor into `ActivateProducer`

- [x] 2.1 Inject `ProducerApprovalGovernance` into `app/Modules/Parties/Actions/ActivateProducer.php` and invoke `guard('Producer', $producer->id)` inside the existing `DB::transaction`, after the locked from-state assert and **before** the KYC gate and the `update` (design D6 order: from-state → operator-principal → distinct-actor → KYC → write). Any violation throws before the write, so `status` and the event log are unchanged.
  - Assert (extend `tests/Feature/Modules/Parties/ProducerLifecycleTest.php`, TDD): creator self-approval → `SeparationOfDutiesViolation`, Producer stays `draft`, 0 `ProducerActivated`; `system` actor → `SeparationOfDutiesViolation`; distinct operator + KYC cleared → `active` + exactly one `ProducerActivated` whose `actor_id` = the approver; a self-approval on a KYC-`pending` Producer is rejected on the **SoD** floor (SoD evaluated before KYC).
- [x] 2.2 Repair the pre-existing Producer-activation tests broken by the new floor: build the `draft` via the real `CreateProducer` Action as operator A, activate as a **distinct** operator B (`actingAs($op,'operator')` / `ActorContext::runAs`), so the KYC/from-state cases still exercise a valid principal. No remaining test relies on `actor_role === System` to activate a Producer.
  - Assert: `ProducerLifecycleTest` green in full; every activation path runs under an operator distinct from the creator.
  - **Done together with 2.1 (inseparable under the green-commit gate).** Blast radius exceeded the named files: the floor also broke `ComplianceChainTest`, `SupplyLifecycleChainTest`, `CatalogLifecycleChainTest` and `ProducerConsoleChainTest` (the last: one operator create+activate → SoD self-approval). All migrated to distinct/vacuous operators; the two SET-WIDE `every(actor_role === System)` chain assertions split (ProducerActivated → NewcoOps, the rest System). Full suite 1969/1969. See lessons.md 2026-07-06 (floor-blast-radius).

## 3. Operator console — surface the affordance, don’t reimplement

- [x] 3.1 Update the Producer `activate` verb on `app/Modules/OperatorPanel/Filament/Resources/Parties/ProducerResource/Pages/ViewProducer.php` to present the **"second actor required"** affordance and map a thrown `SeparationOfDutiesViolation` to a surfaced notification (state unchanged) — the same "surface, not reimplement" contract Catalog uses. Keep the KYC-rejection notification path intact; do not add submit/reject/reopen.
  - Assert: the activate action exposes the affordance; a domain SoD violation renders a notification without changing state.
- [ ] 3.2 Console tests (extend `tests/Feature/Modules/OperatorPanel/Parties/ProducerLifecycleConsoleTest.php`): distinct operators activate a KYC-cleared Producer (happy → `active` + event); creator self-approval via the console → notification, Producer stays `draft`, 0 `ProducerActivated`; the surface exposes the "second actor required" affordance. Migrate existing console activation assertions to distinct operator principals.

## 4. Demo enablement (P2) — a walkable Producer SoD fixture

- [ ] 4.1 Extend the demo seeder (`OperatorDemoSeeder`/`DemoSeeder`, mirroring RM-07’s `seedSodReviewScenario`) with a `draft`, KYC-cleared Producer built through the **real** `CreateProducer` Action as one seeded operator, leaving it activatable only by a distinct seeded operator (genuine creator lineage — a `factory()->create()` row would prove nothing). Keep the production guard.
  - Assert (extend the DemoSeeder test): the fixture Producer is `draft` with a real creator `actor_id`; a distinct seeded operator activates it end-to-end while the creator is blocked on the SoD floor.

## 5. Documentation honesty

- [ ] 5.1 Add a dated in-place correction to `decisions/2026-06-17-approval-separation-of-duties-role-gated.md`: distinguish the SoD floor **built in Catalog** (`catalog-lifecycle-approval`) from **Producer activation**, which shipped KYC-gated single-operator — RM-08 (`parties-producer-approval-sod`) is what makes the "already correct for Parties" claim true. No supersede, no decision-text change; `decisions/INDEX.md` unchanged (mirror the RM-09 in-place-correction precedent).
  - Assert: `git diff` shows only the added correction marker + reworded overclaim clause; the ADR’s Decision/Alternatives/Trade-offs are untouched.

## 6. Verification

- [ ] 6.1 Full suite green on **SQLite and PG17** (the `normalizeActorId` cross-engine `===`); `vendor/bin/phpstan analyse` 0 errors (max); `vendor/bin/pint --test` clean; `openspec validate parties-producer-approval-sod --strict` green. Then run the GUIDE §2.7 semantic-verify pass before the close ritual (tracker RM-08 🔴→✅, `log.md`, `hot.md` — the close ritual proper, outside this list).
