---
type: decision
status: active
date: 2026-06-21
---

## Decision: The OperatorPanel cross-module carve-out admits an operated module's **operand enums** — extending the read-bind / write-through carve-out from `{Models, Actions}` to `{Models, Actions, Enums}`

[[2026-06-19-operator-console-read-binding-write-through-actions]] sanctioned the OperatorPanel composition layer to import each operated module's `Models\*` (read-bind) and `Actions\*` (write-through). This ADR **extends** that carve-out to admit the module's **`Enums`** as well, because a console cannot invoke a write-through Action whose signature requires a domain enum without constructing that enum.

The distinction the discipline rests on (CONTEXT.md → Identity & Access):

- An **operand enum** appears as a **parameter of a domain Action's `handle()`** — e.g. `ClubRegistrationFlowType` on `CreateClub`, or `HoldType` / `HoldScope` on `PlaceHold`. The console must **construct** it (from a form value) to perform write-through. It is part of the Action's public call contract — as inseparable from `Actions` as the Eloquent model is from read-`Models`.
- A **state enum** is an FSM status an Action sets **internally** — e.g. `ClubStatus`, `KycStatus`, `ProducerAgreementStatus`. The console only ever **renders** it, through the model's cast (`->value`), and **never imports or constructs** it (the proven Producer-console pattern).

The console needs to import only operand enums. But the boundary guard (`tests/Architecture/ModuleBoundariesTest.php`) is namespace-**prefix**-based (`pest-plugin-arch`'s `->ignoring()` matches by prefix), so it can admit or forbid only the whole `Parties\Enums\` namespace — it cannot mechanically tell an operand enum from a state enum (they share the prefix). Therefore:

- **Mechanically**, the carve-out admits the whole `Enums` prefix, for the OperatorPanel module only. Whole-module imports and every other internal (`Lifecycle`, `Services`, `Exceptions`, …) stay forbidden, exactly as before.
- **The operand-only scope is a documented discipline**, not a mechanical guard — backstopped by the existing `NoEloquentWriteInOperatorPanelRule` (PHPStan) and the render-state-via-cast convention. A state-enum import would be dead code (you render via the cast, you never construct a status) and is caught in review. A state-enum import appearing is the trigger to revisit a finer (reflection-based) guard.

## Context

- The `operator-console-parties-supply-side` change adds the **Club** operator console. `CreateClub::handle(string $displayName, int $producerId, ClubRegistrationFlowType $registrationFlowType, ?Money $fee = null, …)` (`app/Modules/Parties/Actions/CreateClub.php:43-47`) takes a **required** `App\Modules\Parties\Enums\ClubRegistrationFlowType`. To route the create through the Action (the write-through discipline), the Create page must construct that enum from the form's selected value — which means importing a `Parties\Enums` type.
- The 2026-06-19 carve-out is exactly `{Models, Actions}` (`ModuleBoundariesTest.php:48-51`); its guard test (`:127-151`) explicitly asserts `Enums` and every other internal stay forbidden, pinned so "the exception cannot widen by accident." Its narrative even states the now-falsified assumption (`:25-27`): *"Consoles … render enum casts via their instances, so the cross-module surface stays exactly {Models, Actions} and no later console task needs to widen this list."*
- That assumption was empirically about **state** enums (which consoles only render). It did not foresee an **operand** enum in a create Action. The Producer console — the first Parties console — never hit it because all of `CreateProducer`'s inputs were scalars + a Platform `TranslatableText`. The 2026-06-19 ADR itself already located Action inputs in the UI (`:26` — *"Action input … is the Filament Action's `->form([...])`"*); it simply did not trace the **import** consequence of constructing a typed (enum) input. So this is a **gap** in the original reasoning, not a contradiction of it — hence an extension, not a supersession.
- No in-boundary path exists: `Parties\Contracts` holds only `ComplianceStatus` + `PartyComplianceStatusReader` (no flow-type alias); no Parties Action accepts the flow type as a string; and `pest-plugin-arch` detects fully-qualified references too, so an inline FQ reference cannot dodge the guard.

## Alternatives considered

- **Relocate / re-export operand enums into `Parties\Contracts`** (the already-allowed public surface): rejected. An enum value type is not a contract; moving it churns every internal Parties reference, and minting a parallel public alias is domain code — breaking this slice's pure-operator-surface, no-domain-code discipline.
- **A thin Parties-side input mapper / DTO that accepts the flow type as a string**: rejected. Pushes UI string-parsing into the domain, adds a layer whose only purpose is to dodge an import, and is more code than the import it avoids.
- **A bespoke reflection guard** that admits `Enums` but asserts the OperatorPanel references only enums appearing in an Action signature: rejected **for now**. `pest-plugin-arch` cannot express it (prefix-only); it is a custom reflection test to build and maintain, for a line the no-Eloquent-write rule + the render-via-cast convention already make safe. Revisit only if a state-enum import ever appears.
- **Keep `{Models, Actions}` and defer the Club create surface**: rejected. It guts the Club console (an operator cannot create the very Clubs they would sunset / close) and merely postpones the identical wall for every future operand-enum create (Module S commerce especially).

## Reasoning

1. **An operand enum is the third leg of the write-through contract.** You cannot invoke an Action without constructing its arguments; a typed (enum) argument is as necessary to the call as the Action class itself. The carve-out already admits `Actions` for exactly this reason; admitting their operand enums is the same logic, not new coupling.
2. **It extends, never overturns, 2026-06-19.** The "write-discipline-is-the-real-invariant" core is unchanged; this only traces an import consequence the original left implicit. Every other clause (read-only binding, no `$model->save()`, the no-Eloquent-write architecture test, resource location, capability naming) stands.
3. **Orthogonal to every safeguard.** An enum is a value type: it has no `save()` / `update()` / `delete()` and no DB access, so `NoEloquentWriteInOperatorPanelRule` and Invariant 10's "no cross-module DB access" are untouched. Writes still route through Actions, so the modular-monolith event / audit boundary is untouched.
4. **The render / construct split keeps it narrow.** State enums remain un-imported — rendered via the model cast, the proven Producer pattern — so in practice the console imports an enum only where it constructs an Action argument. The mechanical carve-out is the `Enums` prefix; the operand-only scope is documented (CONTEXT.md: **operand enum** / **state enum**) and review-enforced.

## Trade-offs accepted

- **The mechanical guard is coarser than the conceptual line.** It admits the whole `Parties\Enums` prefix, though only operand enums are meant to be imported. Accepted: a prefix guard is all `pest-plugin-arch` offers; a finer reflection guard is not worth building while a state-enum import is both pointless (rendering uses the cast) and review-visible. The discipline is documented and has a clear revisit-trigger.
- **A third admitted import category widens an exception pinned "cannot widen by accident."** Accepted and deliberate: this ADR is that deliberate act. The guard test's carve-out assertions are updated to admit `Enums` for the OperatorPanel source only (whole-module and lateral modules still forbidden), so the exception still cannot widen further without another deliberate ADR.

## References

- Code: `app/Modules/Parties/Actions/CreateClub.php:43-47` (the operand-enum Action signature); `app/Modules/Parties/Enums/ClubRegistrationFlowType.php` (the operand enum — 4 cases); `tests/Architecture/ModuleBoundariesTest.php:36-55` (`moduleBoundaryAllowedImports` — the carve-out source of truth, widened here), `:127-151` (the carve-out guard test — assertions updated to admit `Enums` for OperatorPanel only); `tests/PHPStan/Rules/NoEloquentWriteInOperatorPanelRule.php` (the write-discipline backstop, unaffected); `app/Modules/OperatorPanel/Filament/Resources/Parties/ProducerResource.php` (the render-state-via-cast pattern this leaves intact).
- ADRs: [[2026-06-19-operator-console-read-binding-write-through-actions]] (**extends** — the `{Models, Actions}` carve-out this widens), [[2026-06-11-modular-monolith-architecture]] (Invariant 10 — module boundaries), [[2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse]] (the non-catalog console pattern this unblocks for Club / ProducerAgreement).
- Spec: `spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md` §1.2 (the console surfaces, does not re-spec, the backend), §1.3 (`actor_role` envelope — fires because the write still routes through the Action).
- CONTEXT.md → Identity & Access (**Operand enum**, **State enum** added this session).
- Implemented by `operator-console-parties-supply-side`, task group 1 (widen `moduleBoundaryAllowedImports` + the guard assertions; the change references this ADR from `design.md`).
