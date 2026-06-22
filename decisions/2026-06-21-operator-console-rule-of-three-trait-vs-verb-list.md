---
type: decision
status: active
date: 2026-06-21
---

## Decision: Rule-of-three review — non-catalog operator consoles CONTINUE to reuse the kit at the TRAIT level; do NOT generalize `OperatorConsoleViewRecord` into a verb-list base, and do NOT add a non-catalog lifecycle View base.

The trait-reuse ADR ([[2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse]]) deferred a verb-list generalization of the catalog View base "to the rule-of-three (≥3 module consoles + ≥1 demand-side console)". That trigger is now met: **Producer + Club + ProducerAgreement** shipped (`operator-console-parties-supply-side`), and **Customer** — the first **demand-side** console, deliberately chosen as the *least kit-shaped surface* — is now designed (`operator-console-parties-customer`). The review concludes: **keep the trait as the shared seam.** Each non-catalog View page extends Filament's `ViewRecord`, `use`s `SurfacesDomainActions`, and assembles its own verb set in `getHeaderActions()`. `OperatorConsoleViewRecord` stays the catalog-only five-verb assembler. No verb-list base is extracted; no second lifecycle base is introduced.

## Context

- The 2026-06-20 ADR's open clause was explicit about what evidence to wait for: *"The right verb-list abstraction (does it need per-verb forms? confirmation affordances? ordering? a shared 'lifecycle column' contract across differently-named state attributes?) is better evidenced after Club + ProducerAgreement … and at least one demand-side console exist."* All four now exist (three shipped, Customer designed) — this ADR answers each of those questions with the evidence.
- **Customer was the stress-test on purpose.** It is the least kit-shaped surface in Module K: three orthogonal lifecycles on one record (status FSM + KYC + sanctions), a co-provisioned Account, multi-Profile membership, and a **composite cross-slice activation gate** (onboarding acceptance + sanctions + KYC). If any console were going to demand a richer shared base, it would be this one.
- What the four non-catalog consoles actually show:
  - **Verb counts and sets diverge, with no convergence**: Producer 6 (`activate`, `retire`, + 4 KYC verbs), Club 2 (`sunset`, `close`), ProducerAgreement 2 (`activate`, `terminate`), Customer 4 (`activate`, `suspend`, `reactivate`, `close`). A "verb-list base" would parameterize a base over a per-entity list — which is exactly what a 4-to-6-line `getHeaderActions()` already is, with **zero** base machinery.
  - **The real divergences live below the verb set**, where a View base would not help: differently-named state attributes (`status` everywhere here, but Customer renders **three** state badges — `status` + `kyc_status` + `sanctions_status` — not one `lifecycle_state`); per-entity read-only context (Customer's infolist surfaces Account status + Profiles + the two compliance axes read-only — unique to it); and the cross-slice activation gate (Customer's `activate` guard spans onboarding + compliance, where the supply-side gates are local). The trait deliberately leaves all of this free.
  - The genuine shared logic — build one uniform write-through action per verb (`lifecycleAction`), run-and-surface success/rejection by `RuntimeException` base type (`surfaceLifecycleOutcome`), narrow the page record (`recordOf`) — already lives in `SurfacesDomainActions`. **The convergence is at the trait, not above it.**

## Alternatives considered

- **Generalize `OperatorConsoleViewRecord` into a verb-list base now** (per-entity verb LIST instead of the fixed five; retrofit all non-catalog consoles). Rejected: the only thing it factors out is the `getHeaderActions()` boilerplate (a few lines per entity), and it pays for that by encoding — in a new abstraction — per-verb forms, confirmation affordances, verb ordering, and a state-column contract across differently-named attributes that the four consoles **do not share**. It would also re-touch shipped, green code (catalog five-verb consoles + the supply-side trio). Net negative: more machinery, no removed duplication of substance.
- **A new sibling base for non-catalog FSMs** (`OperatorConsoleLifecycleViewRecord` taking a verb list). Rejected for the same reason the 2026-06-20 ADR rejected it — it is a second base that does what the trait already does; the demand-side evidence did not change that, it reinforced it (Customer needed *less* shared structure above the trait, not more).
- **Keep deferring** (push the rule-of-three to the next module's console — A/B/…). Rejected: the trigger condition (≥3 consoles + ≥1 demand-side) is met and the four consoles are decisive. Leaving the question perpetually open is itself a cost (every new console re-litigates it). This ADR closes it; a *future* base remains possible but now requires **new** evidence beyond these four.

## Reasoning

1. **The trait already IS the generalization.** The shared write-through/surfacing logic is in `SurfacesDomainActions`; `getHeaderActions()` is intentionally the per-entity *declaration of which verbs*. A verb-list base would move that declaration into data without removing it — abstraction for its own sake.
2. **The least kit-shaped console confirmed it.** Customer — the explicit stress-test — needed nothing the trait does not give. Its novelty (a multi-FSM read-only infolist, a cross-slice gate) is all *below* the verb set, where no View base would help.
3. **Lowest risk, smallest seam** — the same no-premature-abstraction discipline that produced a correct kit (design L9). Shipped catalog and supply-side consoles stay untouched.
4. **Honest base shapes.** `OperatorConsoleViewRecord` stays what it is — the catalog `submit · reject · activate[SoD] · retire · reopen` assembler — rather than being bent to host arbitrary verb lists it would then have to learn to hide.

## Trade-offs accepted

- **`getHeaderActions()` boilerplate repeats per console** (a few lines each). Accepted: it is the single clearest place to read a console's verb set and its wiring to domain Actions, and it keeps each console's real verbs + state columns explicit rather than hidden behind a base's declaration map.
- **This decision is revisitable, but the bar has risen.** If a much later module (A–E finance/inventory ops) surfaces a genuinely shared non-catalog shape, a base can still be extracted — but the trigger is now "**new** evidence beyond the four operator consoles", not "we never evaluated it". The open deferral is closed.

## References

- ADRs: [[2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse]] (the deferral this **resolves** — its open "revisit at the rule-of-three" clause), [[2026-06-20-operator-console-shared-resource-kit]] (the kit; design-L9 no-premature-abstraction discipline), [[2026-06-19-operator-console-read-binding-write-through-actions]] (read-bind / write-through), [[2026-06-21-operator-console-operand-enum-carveout]] (state enums rendered via cast, never imported — the column-rendering convention this leans on).
- Evidence: the four non-catalog consoles — `app/Modules/OperatorPanel/Filament/Resources/Parties/{ProducerResource,ClubResource,ProducerAgreementResource}` (shipped) and the `operator-console-parties-customer` change (design.md D1/D8) — over the trait `app/Modules/OperatorPanel/Filament/Console/Concerns/SurfacesDomainActions.php` and the un-extended catalog base `…/Console/OperatorConsoleViewRecord.php`.
- Trigger: `openspec/changes/archive/2026-06-21-operator-console-parties-supply-side/design.md` (D10 — deferred the verb-list generalization to the Customer console, "the least kit-shaped surface").
