---
type: decision
status: active
date: 2026-06-15
---

## Decision: Authentication-principal models are exempt from the module-table-prefix convention

A model that is an **authentication principal** — one implementing `Illuminate\Contracts\Auth\Authenticatable` — is **exempt** from the module-prefixed-`$table` persistence convention (`tests/Architecture/ModulePersistenceConventionsTest`, the SQL-visible edge of invariant 10), even when it lives inside a module under `App\Modules\**`. It keeps the framework's flat auth-table naming: the OperatorPanel `Operator` maps to `operators`, not `operator_panel_operators`.

The rule is narrow and self-limiting: only a login principal implements that contract; no domain aggregate does. Every non-principal module Eloquent model still MUST declare an explicit `$table` carrying its module prefix. The carve-out is recorded both in the truth spec (`module-architecture` "Module Persistence Conventions", refined via the `operator-auth-foundation` MODIFIED delta) and in the test itself (a `implementsInterface(Authenticatable::class)` skip).

This **forward-binds** the deferred `customer-identity` / `producer-identity` principals: when those guards ship (Module S / TanStack gate) their principal tables inherit the same exemption — a principal that *references* a Module K party by id is still a platform-foundation login shell, not a domain table, so the same reasoning applies.

## Context

`operator-auth-foundation` introduces the **first** Eloquent model that lives inside a module (`App\Modules\OperatorPanel\Models\Operator`, task 2.2). That immediately collided with a pre-existing architecture law authored in `foundations-modules-skeleton`:

- `ModulePersistenceConventionsTest` requires every concrete Eloquent model under `App\Modules\**` to declare a `$table` starting with its module's snake_case prefix — for OperatorPanel that is `operator_panel_`.
- The `operator-identity` delta spec (*"the operator principal (`Operator` / `operators`)"*), the **already-committed** task 2.1 migration (`Schema::create('operators')`) and its test, and task 6.1's acceptance (`hasTable('operators')`) all fix the table as the flat `operators`.

The two are irreconcilable, and the test had no exemption mechanism. The change artifacts had analysed the *other* architecture test (`ModuleBoundariesTest`, the import-boundary law — "stays green with no amendment") but never anticipated this one. The loop escalated (`HUMAN_NEEDED`, iteration 3) rather than weaken a Key Invariant unilaterally; the founder chose this resolution (Option A) on 2026-06-15.

The persistence convention's own truth spec already distinguished **domain tables** (module-prefixed) from **platform tables, owned by no module** (unprefixed, e.g. the event-substrate tables). An auth-principal table is the case the binary missed: a platform-foundation table that happens to be *owned by* a module. This ADR resolves that gap on the side the spec's intent already pointed to.

## Alternatives considered

- **(B) Rename the table `operator_panel_operators`.** Rejected: violates the `operator-identity` delta spec, the committed 2.1 migration + its test, and task 6.1's acceptance; and `operator_panel_operators` is non-idiomatic stutter that diverges from how the framework (and the future customer/producer principal tables) name auth tables.
- **(C) Relocate `Operator` to a platform namespace** (`app/Platform/**` or `app/Models/`), so the persistence test no longer applies. Rejected: directly violates "the Operator model and operator RBAC are **owned by the OperatorPanel module**" — fixed in three places (the `operator-identity` delta spec "Operator Principal", design D1, and the identity-auth ADR). It trades a cosmetic test conflict for a real ownership violation.
- **(A, chosen) Exempt auth principals from the prefix rule** + refine the truth spec + amend the test + this ADR. The only option that honours all the pinned authorities (delta spec, committed migration, task 6.1, the ownership decision) at once.

## Reasoning

1. **Invariant 10's substance is access, not cosmetics.** The invariant forbids *cross-module DB access* (queries, joins, model imports across module boundaries). An auth principal is reached only through its named guard (`Auth::guard('operator')`) — `ActorContext` reads the guard **by name** and imports nothing from OperatorPanel, enforced by `ModuleBoundariesTest`. The module-prefix is a *readability* aid for domain tables; for an auth shell it adds nothing the guard pattern does not already guarantee.
2. **An auth principal is platform-foundation, not a domain aggregate.** The identity-auth ADR frames auth as "a platform foundation"; the principal holds credentials only and has no business lifecycle owned-and-operated cross-module the way a `ProductMaster` or a `Hold` does. The truth spec already exempts platform tables; this extends that to platform tables that live inside a module.
3. **Framework-idiomatic naming.** Laravel's auth table is the flat `users`; the operator principal is its direct replacement (`users` → `operators`). Keeping the flat name keeps the auth surface legible to anyone who knows Laravel.
4. **The amendment mechanism is the spec's own.** `module-architecture`'s "Boundary Enforcement" requirement already prescribes: *amend an architecture law in the change that needs it, justify the test edit in that change's design document.* This is the persistence-dimension analogue — design D7 + the MODIFIED delta + this ADR.
5. **Narrow and forward-stable.** Tying the exemption to the `Authenticatable` contract makes it self-limiting (no domain model implements it) and gives the deferred customer/producer principals a ready, consistent home.

## Trade-offs accepted

- **A Key-Invariant test now carries a documented exception.** Mitigated by anchoring it to a precise contract (`Authenticatable`), keeping the import-boundary law (`ModuleBoundariesTest`) fully intact and unamended, and refining the truth spec so test and spec stay in lockstep (no silent drift).
- **A small precedent for future auth principals.** Deliberate and desired: `customer-identity` / `producer-identity` should not each re-litigate this. The forward-binding is the point.
- **The persistence law is now "domain models only."** Accepted: that was always its intent (it cites "for every entity exactly one module owns the row, lifecycle, and operations"); auth shells were simply never modelled before the first module principal landed.

## References

- Change: `openspec/changes/operator-auth-foundation/` — proposal (operator principal + `operators` table), design **D1** (Operator owned by OperatorPanel; bootstrap `User` removed) + **D7** (this carve-out), the `module-architecture` MODIFIED delta (refines "Module Persistence Conventions"), tasks 2.1 (committed migration), 2.2 (the model), 6.1 (`hasTable('operators')`).
- Test: `tests/Architecture/ModulePersistenceConventionsTest.php` (the exemption skip) · `tests/Architecture/ModuleBoundariesTest.php` (the import-boundary law, unchanged — invariant 10's substance).
- Truth spec: `openspec/specs/module-architecture/spec.md` → "Module Persistence Conventions" (updated on archive of this change), "Boundary Enforcement in the Default Test Suite" (the amendment-in-the-change precedent).
- Invariants: CLAUDE.md #10 (module boundaries — no cross-module DB access), #11 (truth specs change only via change archive).
- ADRs: [[2026-06-15-identity-auth]] (auth is a platform foundation; the Operator principal is owned by OperatorPanel; multi-guard; principal references the party by id for customer/producer) · [[2026-06-11-modular-monolith-architecture]] (boundaries as conventions + tests; amend the law in the change that needs it) · [[2026-06-12-production-db-engine]] (Postgres-truthful migrations).
