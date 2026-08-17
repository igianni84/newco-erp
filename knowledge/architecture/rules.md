# Architecture — Rules (apply by default)

> Promoted from `hypotheses.md` (3 dated confirmations) or derived directly from a canonical decision/arch-test. A contradiction demotes a rule back to `hypotheses.md`.

## The platform substrate speaks storage primitives across the module boundary, never `App\Modules\*` symbols

**Rule.** Code under `App\Platform\**` MUST NOT import any `App\Modules\**` symbol — *including* the `App\Modules\Module` registry enum. The boundary-law arch test `it_forbids_platform_code_from_depending_on_any_module` enforces it (platform is the foundation modules build on; a reverse dependency inverts the layering). Consequence for any platform API that carries a *module identity*: the parameter/column type is **`string`** — the module's registry value (`Module::X->value`) or a platform pseudo-module like `'platform'` — NOT the `Module` enum. The module-side caller owns the typed `Module` and serialises to `->value` at the boundary; the substrate stores/returns the string. Same shape as `event_deliveries.consumer` holding a string FQCN, and `domain_events.module` / `audit_records.module` being plain `string` columns (no `Module` cast).

**Origin (dated).** 2026-06-12, `foundations-domain-events-audit` task 3.3: `AuditRecorder` was first written `Module|string $module` (per design D3's sketch) and the full suite turned the arch test RED — `Expecting 'App\Platform' not to use 'App\Modules'.`. Refining the signature to `string $module` (drop the `Module` import; callers pass `Module::X->value`) returned it green. Design D3 explicitly labels its signature *"realization, loop may refine"*; design D1 ("platform must never depend on modules") + the arch test are canonical and outrank a realization detail they conflict with.

**Applies to.** `DomainEventRecorder` / `AuditRecorder` (`string $module`); every F2+ module emitter (`$recorder->record(..., module: Module::Commerce->value, ...)`); the `*.module` columns. Tests MAY import `Module` (the arch suite scans only `App\Platform` source, not `Tests\`). Document the call convention in `docs/event-substrate.md` (task 6.1).

## Within-module Eloquent relations ARE allowed — invariant 10 / `ModuleBoundariesTest` bans only CROSS-module relations

**Rule.** Invariant 10 forbids cross-module Eloquent relations, joins, and model imports — modules talk across boundaries via domain events + read contracts. It does **not** touch relations between two tables **owned by the same module**: a neutral core ↔ its per-type attribute table, or a parent ↔ child within one module, is a legitimate intra-module relation and needs **no `ModuleBoundariesTest` amendment** (the test stays green unchanged). A relation to a same-namespace model needs no `use` import (the FQCN resolves directly); annotate with the modern generic form `@return HasMany<Club, $this>` / `BelongsTo<Producer, $this>`. (Contrast: a *cross*-module read goes through a projection + bare-string event names, never a relation — see the platform-substrate rule above.)

**Confirmations (dated, cross-change).** 2026-06-15 `catalog-product-spine` (`ProductMaster::wineAttributes()` `hasOne`, `ProductVariant::master()` `belongsTo`, `CompositeSku::constituents()` `belongsToMany`); 2026-06-15 `parties-core` (Club→Producer, ProducerAgreement's two FKs, Customer/Account, Profile — all within Module K); 2026-06-16 `parties-producer-lifecycle` (`Producer::clubs()` — first within-module inverse `hasMany`); 2026-06-16 `catalog-lifecycle-approval` (gates read a sibling spine's `lifecycle_state` directly within Module 0). In every case `ModuleBoundariesTest` stayed 2/2, no amendment.

**Applies to.** Modeling an entity's own sub-tables or same-module parent/child links. The cross-module ban still holds for any reference to **another** module's tables.

## An Action injects `DomainEventRecorder` + `ActorContext` IFF it records a domain event — an audit-only / eventless Action injects neither

**Rule.** A module write Action's constructor dependencies are decided by ONE question: *does this Action record a domain event?*
- **Evented** Action (a spine `Create*`, an FSM transition that emits, a screening/completion recorder): injects **`DomainEventRecorder` + `ActorContext`**, and inside its single `DB::transaction` writes the model **and** `$recorder->record(...)` with the actor envelope.
- **Eventless / audit-only** Action (a KYC transition that only mutates state under design L3; a *thin* satellite-entity `Create*` with no event): injects **nothing** — no constructor at all — and is invoked via a zero-arg container build `app(X::class)->handle($id)`. An injected-but-unused recorder is dead code the codebase deliberately avoids.

Decide by behaviour, not surface symmetry: sibling Actions in one slice legitimately differ in their constructor because one emits and one doesn't. When a formerly-eventless Action later needs to emit (or to orchestrate a within-module sibling), add the dependency **then**. A multi-event Action selects the event class by a `match (true)` on the outcome and reads `$event::NAME` / `$event::payload($model)` — no base class, no magic string; a `default => null` arm absorbs the event-silent outcomes.

**Confirmations (dated, cross-change).** 2026-06-17 `parties-compliance` (origin & richest — the KYC Actions have NO constructor per design L3; `RecordCustomerScreening` is the first *evented* compliance Action and injects recorder+actor like the spine); 2026-06-18 `parties-holds` (`PlaceHold` evented); 2026-06-19 `parties-membership-activation`; 2026-06-19 `parties-membership-suspension`; 2026-07-02 `parties-anonymisation` (the *thin* eventless `CreateCustomerAddress` — `Model::create()` only, no recorder, no transaction — is the documented counter-example); 2026-07-08 `parties-module-k-br-guards`.

**Applies to.** Every module write Action. Pairs with the platform-substrate rule above (the recorder carries a **`string`** module identity across the boundary) and the closing-chain integration rule in `knowledge/testing/rules.md` (factories bypass the recorder, so they record nothing).
