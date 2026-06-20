---
type: decision
status: active
date: 2026-06-19
---

## Decision: The OperatorPanel is the composition layer — its Filament resources read-bind to module Eloquent models (read-only, enforced); every write routes through the owning module's domain actions

Opens the **Operator Admin UI** build — the first operator-facing surface over the shipped Module 0 (Catalog) and Module K (Parties) backends. The Filament shell from `operator-auth-foundation` has **zero resources** today; STEP 1 settles how an Eloquent-native Filament 5 panel reads and writes module entities without breaking Invariant 10 (no cross-module DB access; events + contracts only) or the no-model-leak boundary law. Decided in a grill-with-docs session (2026-06-19, four questions, all founder-confirmed).

### The tension

Filament 5 is Eloquent-native: a Resource binds to a model, and its tables/forms/filters/sorting/pagination are built on that model's query builder. But the OperatorPanel is module #9 ([[2026-06-11-modular-monolith-architecture]]), so a `ProductMasterResource` that imports `App\Modules\Catalog\Models\ProductMaster` is *literally* a cross-module model import — the thing Invariant 10 forbids. The existing cross-module read pattern (`PartyComplianceStatusReader` → PII-free `ComplianceStatus` DTO; `app/Modules/Parties/Contracts` + `Reads`, bound in `PartiesServiceProvider`) was built for **lateral business coupling** (Module S asking K "is this scope clear to transact?") and is deliberately PII-free — actively wrong for an operator surface whose job is to render and edit those very entities (an operator editing a Customer's address *needs* the PII).

### Reads: the OperatorPanel may bind Filament resources to module Eloquent models, read-only

Invariant 10's *substance* is "no lateral business-module coupling, no DB-access bypass of the event/contract API" — not the cosmetic fact of an `import`. This repo already drew that distinction: [[2026-06-15-auth-principal-table-naming]] held that "invariant 10's substance holds via the import-boundary test, not the cosmetic prefix." The OperatorPanel is **not a lateral peer**; it is the top-down **composition / operator-surface layer** that Architecture §2.1/§2.3 defines as owning **no entities** and existing to **"operate the modules' entities"** (the manual-first load-bearing operational surface, D24). A read-only dependency from the composition root onto the modules it composes is the legitimate direction, and is hereby **sanctioned and scoped**:

- **Scope:** only the `OperatorPanel` module; only **read/display**; only within that module's own console (the Catalog console reads `Catalog\Models\*`, the Parties console reads `Parties\Models\*`).
- **Not** a general license: lateral business modules (S↔K, etc.) keep the strict events-+-contracts boundary unchanged.

### Writes: every mutation routes through the owning module's domain action — never `$model->save()`

This is the half that makes the read exception safe. The resource is a **read-projection**; **every** mutation is a Filament **Action** wired to the module's existing domain action:

- **Domain operations → Filament Actions** (most Module 0/K capabilities are operations, not CRUD): the catalog lifecycle transitions via `app(SubmitProductMasterForReview)` / `app(ActivateProductMaster)` / the retire transition (all delegating to `LifecycleTransition`); `app(PlaceHold)` / `app(LiftHold)`; `app(ActivateProducer)`; the one membership approve/decline write; ProducerAgreement draft/activate; suspension; GDPR erasure. Action input (e.g. Place Hold's type/scope/reason) is the Filament Action's `->form([...])`.
- **Create → Filament Create page** with `handleRecordCreation()` overridden to call `app(CreateProductMaster::class)->handle(...)`.
- **Edit metadata → Filament Edit page** with `handleRecordUpdate()` overridden to call the module's update action.
- The default mutating paths (implicit form `save()`, `DeleteAction`) are **disabled** — no UI path writes a module model directly.

Routing through the action fires the FSM guards (inside `DB::transaction()` + `lockForUpdate()`), the domain events, and the `actor_role` audit envelope. `ActorContext` (`app/Platform/Events/ActorContext.php`) already resolves an operator on the `operator` session guard to (`newco_ops`, `Operator.id`) — so an operator-driven write is stamped `actor_role: newco_ops` automatically ([[2026-06-15-identity-auth]]; Admin Panel §1.3). The panel never re-implements a domain rule; it surfaces it and lets domain exceptions surface as Filament notifications. Two rules it relies on, not reimplements:

- **Separation of duties** (Creator→Reviewer→Approver, self-approval never allowed) is enforced by `ApprovalGovernance` inside `LifecycleTransition` ([[2026-06-17-approval-separation-of-duties-role-gated]]; Admin Panel §5.2). The panel exposes the "second actor required" affordance and calls the action; a colliding actor is rejected by the domain.
- **Hold-lift discipline per type** ([[2026-06-18-hold-lift-discipline-per-type]]): the panel offers Lift only for the four operator-liftable types, but `HoldType::autoLiftable()` + the `LiftHold` guard remain the authority.

### Two structural safeguards keep the read exception honest

1. **Module models carry no cross-module Eloquent relations** (Invariant 10 already forbids them at the model layer). So even though the panel binds to a model, Filament eager-loading/filtering *cannot* traverse into another module — the boundary holds by construction, not by discipline. A `ProductMaster` has `producer_id` + the Catalog-local `catalog_producer_states` projection, not a `->producer()` relation into Parties.
2. **An architecture test** (Pest, with PHPStan support) asserts that no class in `App\Modules\OperatorPanel\**` performs an Eloquent write (`save`/`update`/`create`/`delete`/`forceDelete`/mass-assignment) on a module model — making "reads-only + writes-through-actions" enforced by CI, not convention. This test is part of the definition of done, not optional.

### Derived cross-module status still flows through read contracts

The exception is for displaying a module's **own** entities. When a console needs **derived cross-module** state — e.g. "is this Customer clear to transact?" — it uses the existing read contract (`PartyComplianceStatusReader::forCustomer()`), not ad-hoc model spelunking. The DTO/Contracts pattern remains the only cross-module *business* read.

### Resource location & capability naming

- **Location:** `app/Modules/OperatorPanel/Filament/{Resources,Pages,Widgets}`, grouped by operated module (`Resources/Catalog/…`, `Resources/Parties/…`; namespace `App\Modules\OperatorPanel\Filament\Resources\Catalog\…`). The `discoverResources/Pages/Widgets` calls in `app/Modules/OperatorPanel/Providers/AdminPanelProvider.php` (currently pointing at the top-level `app/Filament/**` left by the shell) are repointed. Rationale: in a modular monolith where all code lives under `app/Modules/{Module}/`, the operator surface *is* OperatorPanel code; keeping it inside the module makes the module self-contained and sets the standard for the seven module consoles still to come.
- **Capability:** the openspec capability is **`operator-console`** (sibling of the existing `operator-identity`; the module is `OperatorPanel`). "Admin Panel" is the PRD's name for the whole 9th cross-cutting surface and remains the cited *source*; `operator-console` is the living-spec capability that describes it. Both planned changes — Catalog console first (proving the pattern end-to-end), then Parties console — delta into `operator-console`.

## Context

- The kickoff: build the first operator UI over the two fully-shipped backends (Module 0 ~94 PHP files, Module K ~107 PHP files). The `operator-auth-foundation` change left a Filament panel shell (`/admin`, `operator` guard, login + password reset + opt-in TOTP, bare spatie roles Creator/Reviewer/Approver) but **zero resources** — STEP 1 must settle the read/write pattern before any resource is built.
- Binding constraints: Invariant 10 (module boundaries — root `CLAUDE.md`); the no-model-leak boundary law (`docs/module-template.md` §3 — a module may depend only on another module's `Contracts\*`/`Events\*`); Invariant 8 (audit envelope `actor_role`); the Admin Panel PRD's posture that it "does NOT write a UX/layout spec" (§1.2) and is **role-agnostic** at the PRD layer with authority-tier RBAC deferred to `feedback_prd_rr_approval` (§1.4), owning exactly **one** discipline — the multi-actor / no-self-approval patterns (§5.2).
- The spec is explicit that the Admin Panel "owns no entities — it operates the modules' entities" (Architecture §2.1, §2.3) — the architectural license for the read exception.

## Alternatives considered

- **Strict purity — every read through a per-module DTO read contract** (extend `PartyComplianceStatusReader` to everything the panel displays): rejected. It fights Filament frontally (you forfeit native Eloquent tables, eager loading, filters, sorting, pagination → large bespoke table/form code for every entity), and it is conceptually wrong here — those contracts are PII-free by design for *business* coupling, whereas the operator surface must show the PII it exists to manage. High cost, negative value.
- **Pragmatic but unprincipled — bind to models for reads AND let resources `save()` directly:** rejected. It would bypass FSM guards, domain events, and the `actor_role` envelope — violating Invariants 7/8 and the spec's "every operator action carries the audit envelope" (§1.3). The read exception is only defensible *because* writes are corralled into the domain actions.
- **Resources in the shell's default `app/Filament/**`:** rejected for module-consistency — it detaches the operator surface from its module in a codebase where everything else lives under `app/Modules/`.
- **Capability `admin-panel`:** not chosen — the living-spec family uses the `operator-*` prefix (`operator-identity`); `admin-panel` is the PRD title, kept as the cited source, not the capability id.

## Reasoning

1. **Invariant 10 protects against lateral coupling and DB-access bypass, both preserved** — the OperatorPanel is the composition root (top-down), business modules stay strictly separated, and no write skips the event/contract API.
2. **The write discipline is the real invariant** — corralling every mutation into a domain action keeps the FSM/event/audit guarantees the invariants exist to protect; the read binding is cosmetic by comparison and is fenced by an architecture test.
3. **The model layer already enforces the boundary** — no cross-module relations means Filament physically cannot join across modules, so the read exception cannot silently grow into cross-module querying.
4. **Filament does its job** — native scaffolding for reads, explicit Actions for the (mostly non-CRUD) domain operations; minimal code, idiomatic, and the SoD/Hold rules stay in the domain where they are already tested.
5. **Module-consistency compounds** — putting the surface under `OperatorPanel/` sets the pattern for the seven consoles still to come (A/D/S/B/C/E + the cross-module finance-ops / Logilize consoles the manual-first defers created).

## Trade-offs accepted

- **A sanctioned exception to "no cross-module model imports"** — narrow (OperatorPanel only, read-only) and CI-enforced, but it *is* an exception; a future reader must understand it is deliberate (hence this ADR + the architecture test naming it).
- **Discipline shifts from "no import" to "no write"** — protection now depends on the architecture test catching an Eloquent write inside the OperatorPanel namespace. If that test is weak or removed, the boundary erodes silently — so the test is part of the definition of done.
- **Repointing discovery** touches `AdminPanelProvider` (app code, not protected) and abandons the Filament default location — a one-time, low-risk move every later console benefits from.
- **`operator-console` ≠ the PRD's "Admin Panel" name** — a small naming indirection (capability vs PRD title), documented here and in CONTEXT.md so the two never read as different things.

## References

- Spec: `spec/02-prd/Architecture_v0.3-MVP.md` §2.1, §2.3 (Admin Panel = first-class cross-cutting operator surface; "owns no entities — operates the modules' entities"; manual-first load-bearing, D24); `spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md` §1.2 (not a UX spec; does not re-spec backends), §1.3 (`actor_role` envelope), §1.4 (role-agnostic; authority-tier deferred), §5.2 (multi-actor / no self-approval); `spec/03-acceptance/Admin_Panel_Acceptance_v0.3-MVP.md` (AC-AP-INV-0 / INV-K, AC-AP-MA-1, AC-AP-PWB-6).
- Invariants: root `CLAUDE.md` #7 (Holds never auto-lifted — see [[2026-06-18-hold-lift-discipline-per-type]] for the per-type nuance), #8 (audit envelope), #10 (module boundaries).
- Code: `app/Modules/OperatorPanel/Providers/AdminPanelProvider.php` (panel shell; discovery to repoint); `app/Platform/Events/ActorContext.php` (`newco_ops` resolution); `app/Modules/Parties/Contracts/PartyComplianceStatusReader.php` + `app/Modules/Parties/Reads/DatabaseComplianceStatusReader.php` (the cross-module read-contract exemplar); `app/Modules/Catalog/Lifecycle/LifecycleTransition.php` + `app/Modules/Catalog/Actions/*` and `app/Modules/Parties/Actions/*` (the domain actions writes route through); `docs/module-template.md` §3 (boundary law).
- ADRs: [[2026-06-11-modular-monolith-architecture]] (Invariant 10; OperatorPanel = module #9; no-new-dep rule), [[2026-06-15-identity-auth]] (operator = Filament session guard → `newco_ops`; spatie RBAC; SoD as module logic), [[2026-06-15-auth-principal-table-naming]] (Invariant 10 = substance via import-boundary test, not cosmetics — the precedent), [[2026-06-17-approval-separation-of-duties-role-gated]] (SoD floor), [[2026-06-18-hold-lift-discipline-per-type]] (Hold-lift authority), [[2026-06-11-stack-versions-and-filament-ai-tooling]] (Filament 5.x).
- CONTEXT.md → Identity & Access (**Operator console** added this session; **Operator**, **Actor context**).
- Capability: `operator-console` (new; sibling of `operator-identity`). Implemented by the forthcoming Catalog-console change (STEP 2), then the Parties-console change.
