## Why

Producer activation in the Parties module ships with **no separation-of-duties floor**: `ActivateProducer` accepts any actor — including the default `System` actor and any single operator who also created the Producer. The Catalog module already enforces the spec-mandated four-eyes floor on every commercial-impact activation (Admin Panel PRD §5.2; `product-catalog` *Approval Governance*), but Parties does not, so the "distinct actors; self-approval never allowed" control that **AC-K-J-10** requires for Producer onboarding is unimplemented — on the very upstream entity whose activation gates Product Master activation (AC-K-BR-Producer-2 / XM-2). RM-08 closes that parity gap before Paolo's Module-K validation, and removes the "System actor accepted" hole surfaced in the verdict.

## What Changes

- Add a **distinct-actor separation-of-duties floor** to `ActivateProducer` (`draft → active`). The activating actor SHALL be an **authenticated `newco_ops` operator** (a `system`/null actor is rejected) **and** SHALL differ from the **Producer's creator** (the actor on the `ProducerCreated` event). This mirrors Catalog's `ApprovalGovernance` at the spec-admissible **2-step Creator → Approver** depth. The existing **KYC-cleared gate is unchanged — both gates apply**.
- **BREAKING (internal, not a public API):** Producer activation now requires a distinct authenticated operator; call sites that activate under the default `System` actor (existing tests, `DemoSeeder`) must run under distinct operator principals via `ActorContext::runAs` (the RM-07 pattern).
- **Console:** the Producer activate verb SHALL present the **"second actor required"** affordance; a same-actor or non-operator violation is rejected by the domain and surfaced as a notification. The console does not reimplement the floor.
- New Parties-local SoD guard + a localized violation exception + EN/IT copy.
- Extend the demo seeder with a **Producer SoD fixture** (created by one operator, activatable by a distinct one), mirroring RM-07's Catalog fixture, so the P2 walkthrough runs end-to-end.
- **Honesty fix:** correct **in place** the overclaim in `decisions/2026-06-17-approval-separation-of-duties-role-gated.md` that Producer SoD "was already built in `parties-producer-lifecycle`" — Producer activation in fact shipped KYC-gated single-operator; RM-08 is what makes the claim true (mirrors the RM-09 in-place correction; no decision change, ADR stays `active`).

**Deliberately NOT in this change (slice boundary):**
- **Membership / Profile approval SoD** (`ApproveProfile`). No AC imposes distinct-actor on membership (J-4/J-13/J-14/FSM-2 do not); the Admin Panel PRD treats membership approve/decline as *the one producer write* (single-actor). Adding SoD there is an extension **beyond the frozen spec** that would need its own mini-ADR — deferred to a future change (decision confirmed with Giovanni 2026-07-06).
- **A `reviewed` state / submit / reject / re-submit review FSM on Producer** (the full 3-step Creator → Reviewer → Approver depth). The Producer FSM stays linear (`draft → active → retired`); the reviewer leg has no persisted source in Parties. Building it is a larger, separate change.
- **SoD on `ActivateProducerAgreement`, `ActivateCustomer`, `ActivateProfile`** — none is named by AC-K-J-10 or Admin Panel §5.2's Parties pattern.
- **Any admin-configurable distinct-actor toggle** — the resolved ADR 2026-06-17 keeps the floor **non-configurable** ("two distinct people; self-approval never allowed"). No config knob ships.
- **Platform-level extraction** of the shared SoD guard (Parties gets its own; module boundaries forbid importing Catalog's).

## Capabilities

### New Capabilities

_None. This change adds no new capability spec._

### Modified Capabilities

- `party-registry`: the *Producer Lifecycle* requirement gains the separation-of-duties floor on `ActivateProducer` (operator-principal + distinct-actor gate, alongside the unchanged KYC gate).
- `operator-console`: the *Operator advances a Producer through its supply-side status lifecycle* requirement flips from "the activate action presents **no** second-actor affordance … not the catalog separation-of-duties governance" to the SoD affordance + domain-enforced rejection.

## Impact

- **Code (`app/Modules/Parties/`):** new SoD guard (reads the platform `DomainEvent` store for the creator + `ActorContext` for the approver — no Catalog import, module boundary preserved); new localized `…Violation` exception; wire the guard into `Actions/ActivateProducer.php`. Console `OperatorPanel/…/ProducerResource/Pages/ViewProducer.php` activate verb. `database/seeders/…DemoSeeder` Producer SoD fixture. `lang/en/parties.php` + `lang/it/parties.php` copy.
- **Tests:** migrate existing Producer-activation tests (`ProducerLifecycleTest`, `Parties/ProducerLifecycleConsoleTest`, and any DemoSeeder assertion) to distinct operator principals; add SoD tests mirroring `ProductMasterLifecycleTest` (creator self-approval blocked, system/non-operator blocked, distinct operator + KYC-cleared succeeds).
- **Docs:** in-place honesty note on `decisions/2026-06-17-approval-separation-of-duties-role-gated.md`.
- **No migration** (no schema change — the creator is read from the existing `domain_events`), **no new dependency**, **no new domain event**. `ProducerActivated` is unchanged.
- **Traceability note (deliberate gap):** AC-K-J-10 at the full **3-step** (with a distinct Reviewer) depth is **not** delivered; RM-08 delivers the **2-step Creator → Approver** depth — the configured depth the spec explicitly admits (Module K PRD §4.4 / §0 Q3; `product-catalog` *Approval Governance* two-step path). The reviewer/`reviewed`-state build is out of slice.
