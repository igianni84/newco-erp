---
type: decision
status: active
date: 2026-06-20
---

## Decision: A non-catalog operator console reuses the shared kit at the TRAIT level — `SurfacesDomainActions` + `OperatorConsoleCreateRecord` + `OperatorConsoleResource`'s label helpers — and assembles its OWN lifecycle verb set; it does NOT extend the catalog-shaped `OperatorConsoleViewRecord` (the fixed five-verb governance assembly) nor use `lifecycleStateColumn()` (the `lifecycle_state` attribute). Revisit a verb-list generalization of the View base only after ≥3 module consoles confirm a shared non-catalog shape (rule-of-three).

The shared operator-console kit (ADR [[2026-06-20-operator-console-shared-resource-kit]]) was extracted from, and proved against, the **catalog** lifecycle: a `draft → reviewed → active → retired` review-and-approval governance with a Creator → Reviewer → Approver separation-of-duties floor. That shape is baked into two of the kit's four pieces:

- `OperatorConsoleViewRecord::getHeaderActions()` hard-codes the **five** catalog verbs — `submit · reject(notes) · activate(second-actor affordance) · retire · reopen` — built from a per-entity `lifecycleInvocations()` map keyed by exactly those five verbs.
- `OperatorConsoleResource::lifecycleStateColumn()` reads the attribute literally named `lifecycle_state`.

The Parties (Module K) supply-side entities do **not** share that shape (verified in `app/Modules/Parties/`): **Producer** is `draft → active → retired` (two operator verbs — activate, retire — no submit/reject/reopen), its activation is **KYC-gated, not SoD-gated**, and its state attribute is `status` (a `ProducerStatus` enum), not `lifecycle_state`. **Club** is `active → sunset → closed` (born active, no activate). **ProducerAgreement** is `draft → active → superseded|terminated`. None maps onto the five-verb catalog assembly or the `lifecycle_state` column.

The other two kit pieces, and the trait's primitives, ARE lifecycle-agnostic and reused verbatim:

- `OperatorConsoleCreateRecord` — the write-through create template (`handleRecordCreation` → `createViaAction`, the localized-`RuntimeException` → `data.<createRejectionField()>` form-error catch). It encodes the read-bind / write-through discipline (ADR [[2026-06-19-operator-console-read-binding-write-through-actions]]), nothing catalog-specific.
- `SurfacesDomainActions` (trait) — `lifecycleAction(verb, successKey, invoke, ?form, ?confirmationKey)` builds **one** uniform write-through action for **any** verb; `surfaceLifecycleOutcome()` runs a domain action and renders success/danger (catching domain rejections by their `RuntimeException` base type — `IllegalProducerTransition` and `IllegalKycTransition` both extend it, so they surface for free with no `Parties\Exceptions` import); `recordOf()` narrows the page record to the concrete model. It is the catalog `OperatorConsoleViewRecord`'s **own** dependency — the View base is just one catalog-shaped *assembler* on top of this trait.
- `OperatorConsoleResource`'s `getModelLabel()/getPluralModelLabel()` (off a per-entity `i18nKey()`) and `versionColumn()` (all `parties_*` entities except Supplier carry the same optimistic-lock `version` column the catalog entities do).

**So a non-catalog console reuses the kit where it is lifecycle-agnostic and diverges only where the catalog FSM is encoded:** the View page extends Filament's `ViewRecord`, `use`s `SurfacesDomainActions`, and returns its own verb set from `getHeaderActions()` built with the same `lifecycleAction` factory (Producer: `activate` [no second-actor affordance] + `retire` + the four KYC verbs); the Resource extends `OperatorConsoleResource` (label methods + `versionColumn()` reused) but supplies its own `status`/`kyc_status` badge columns instead of `lifecycleStateColumn()`.

## Context

- The shared-kit ADR's own trade-off line anticipated this: *"the kit is shaped from Catalog's seven entities, and a later module (e.g. K's holds, or E's finance ops) may need a seam the kit lacks. Accepted: the kit is extended (guarded) when that lands … it is a starting template, not a frozen framework."* This ADR is the first such "when that lands", and it finds the seam is **smaller than an extension** — the trait already IS the shared layer; only the catalog-specific *assembler* (the View base) and the `lifecycle_state` *column name* don't generalize.
- Implemented by the `operator-console-parties-producer` change (the first Parties console). The catalog consoles are untouched: their `OperatorConsoleViewRecord` and `lifecycleStateColumn()` keep working exactly as shipped (six spine consoles + Master, all green).
- The trait carries `abstract i18nKey(): string`, so a View page that `use`s it directly satisfies the same contract the catalog View base did — no new abstraction is introduced, only used one level lower.

## Alternatives considered

- **Generalize `OperatorConsoleViewRecord` now** — refactor it to build header actions from a per-entity declared verb-LIST (instead of the fixed five), and retrofit all seven catalog consoles onto the generalized base. Rejected **for now**: it is a risky refactor of shipped, green code driven by a sample size of one non-catalog module. The right verb-list abstraction (does it need per-verb forms? confirmation affordances? ordering? a shared "lifecycle column" contract across differently-named state attributes?) is better evidenced after Club + ProducerAgreement (the rest of the supply-side trio) and at least one demand-side console exist. This is the same no-premature-abstraction discipline (design L9) the kit itself followed — extract/​generalize on the *third* confirming case, not the first.
- **A new sibling base class for non-catalog FSMs** (e.g. `OperatorConsoleLifecycleViewRecord` taking a verb list). Rejected: it adds a second base class that does what the trait already does; until the shared non-catalog shape is evidenced it would itself be a guess, and it splits "how a console view works" across two base classes for no current benefit. Using the trait directly is strictly less machinery.
- **Force Producer into the five-verb base** (e.g. map activate→activate, retire→retire, leave submit/reject/reopen as no-ops or hidden). Rejected: it would surface verbs the Producer FSM does not have (or require the base to learn to hide them), inverting the dependency — the catalog base would have to know about non-catalog shapes. The console must mirror the domain FSM, not bend it to a foreign one.

## Reasoning

1. **Reuse where it is real, diverge where it is encoded.** Three of the four kit pieces (the create base, the trait, the resource label/version helpers) are lifecycle-agnostic and reused verbatim; only the View *assembler* and the `lifecycle_state` *column name* are catalog-specific, and the divergence is a few lines (own `getHeaderActions()`, own status column) — not a fork.
2. **Lowest risk to shipped code.** The catalog base classes are not touched, so the seven green catalog consoles cannot regress from this change.
3. **No premature abstraction.** A verb-list generalization is a real future option, but the evidence for *its* shape is one module today; deferring it to the rule-of-three is the same discipline that produced a correct kit.
4. **The console still surfaces, never reimplements.** The KYC-gate (`IllegalProducerTransition::kycNotCleared`), the from-state guards, and the retire→Club-sunset cascade are all domain behaviour the trait surfaces by base-type catch — exactly as the catalog gates are. The divergence is purely *which verbs* the view assembles, not *how* a write or a rejection is handled.

## Trade-offs accepted

- **Two view-page shapes coexist** — catalog consoles extend `OperatorConsoleViewRecord`; non-catalog consoles extend Filament `ViewRecord` + `use SurfacesDomainActions`. Accepted: the trait is the genuine shared layer and both shapes route through it identically; the View base is explicitly documented as the *catalog* assembler. The divergence reads as intentional, not as drift.
- **A future verb-list generalization will re-touch these consoles** — if the rule-of-three later justifies generalizing the base, Producer/Club/Agreement get retrofitted onto it. Accepted: that retrofit is cheap (they already route through the trait) and is the deliberate trigger, not a cost of this decision.
- **Each non-catalog console writes its own status column** — a one-line `TextColumn::make('status')->badge()` per entity rather than a shared helper. Accepted: trivial, and it keeps the entity's real attribute name (`status`, `state`, `sanctions_status`, …) explicit rather than aliased to `lifecycle_state`.

## References

- ADRs: [[2026-06-20-operator-console-shared-resource-kit]] (the kit this reuses; its trade-off line predicted this seam), [[2026-06-19-operator-console-read-binding-write-through-actions]] (read-bind / write-through discipline + the `{Models, Actions}` carve-out the trait honors), [[2026-06-17-producer-kyc-gate-not-required-clears]] (the KYC-cleared semantics the Producer activate surfaces), [[2026-06-11-modular-monolith-architecture]] (OperatorPanel owns no entities; kit lives under `app/Modules/OperatorPanel/Filament/`).
- Kit code: `app/Modules/OperatorPanel/Filament/Console/Concerns/SurfacesDomainActions.php` (the trait reused directly), `OperatorConsoleCreateRecord.php` (reused verbatim), `OperatorConsoleResource.php` (`getModelLabel`/`getPluralModelLabel`/`versionColumn` reused; `lifecycleStateColumn` NOT used), `OperatorConsoleViewRecord.php` (the catalog five-verb assembler NOT extended).
- Domain: `app/Modules/Parties/Actions/{ActivateProducer,RetireProducer,RequireProducerKyc,WaiveProducerKyc,RecordProducerKycVerified,RecordProducerKycRejected,SunsetClub}.php`; `app/Modules/Parties/Exceptions/{IllegalProducerTransition,IllegalKycTransition}.php` (both extend `RuntimeException`); `openspec/specs/party-registry/spec.md` (Producer Lifecycle; Producer KYC Lifecycle; Supply-Side Lifecycle Events).
- Implemented by change `operator-console-parties-producer`; the template for `operator-console-parties-supply-side` (Club + ProducerAgreement) and the later demand-side consoles.
