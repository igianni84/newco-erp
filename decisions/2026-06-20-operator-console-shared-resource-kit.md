---
type: decision
status: active
date: 2026-06-20
---

## Decision: Extract a shared operator-console kit (base read-only Resource + an operator-action wrapper + base Create/View pages) and retrofit Product Master onto it, so all seven catalog consoles share one structure and no console is a bespoke special case

`operator-console-catalog-master` shipped the first console and **deliberately deferred** the abstraction: its design **L9 — "No premature abstraction. With only Product Master in this change, do not extract a shared base Resource or an 'operator action' wrapper. The right abstraction emerges from the second entity (`operator-console-catalog-spine`); extracting now would guess."** This change is that second entity — in fact the next **six** (Product Variant, Format, Product Reference, Case Configuration, Sellable SKU, Composite SKU). With seven entities repeating one console shape, the abstraction is now evidenced, not guessed, so we extract it here and bring Product Master back onto it.

The kit lives inside the OperatorPanel module (`app/Modules/OperatorPanel/Filament/`) and is the structural template every later console reuses — the **Parties** console next, then the **A/D/S/B/C/E** module consoles. It changes **no** capability contract and **no** invariant: it is an internal code-organisation decision, recorded as an ADR because it is forward-binding for ~eight future consoles and a future engineer building one needs to know to extend it rather than reinvent (and why Master is not a special case).

### What the seven consoles share (the evidence for the kit)

Verified across the shipped Master console and the six spine backends:

- **A read-only Resource** bound to a Catalog model (ADR `2026-06-19`): a `lifecycle_state` badge column + a `version` column, a list + a view, localized labels from an `operator_console.<entity>.*` key root, and **no default create/edit/delete mutating action**.
- **A Create page** whose `handleRecordCreation(array $data): Model` routes through `app(Create<Entity>)->handle(...)` and returns the model — never `$model->save()`.
- **A View page** with the **uniform five** lifecycle header actions — submit · reject (notes form) · activate (with the "second actor required" affordance) · retire · reopen — each wired to `app(<Action>)->handle($record[, $notes])` and each surfacing a thrown domain rejection as a Filament danger notification (the `surfaceLifecycleOutcome` try/notify shape, today a **private static method duplicated** on the View page).

### What the kit extracts

1. **`OperatorConsoleResource`** (base `Filament\Resources\Resource`): the read-only conventions — `getModelLabel()/getPluralModelLabel()` resolved from a per-entity `i18nKey()`; a shared helper for the `lifecycle_state` badge + `version` columns; and the "add no mutating action" default. Per-entity subclasses set `$model`, `i18nKey()`, and the entity-specific columns / infolist entries / create-form fields.
2. **The operator-action wrapper** (a trait/concern, e.g. `SurfacesDomainActions`): promotes `surfaceLifecycleOutcome(Closure $run, string $successTitle)` to one shared place, plus a small factory that builds a standard lifecycle `Filament\Actions\Action` from `(verb, Action invocation, success key, optional form, optional confirmation affordance)`. This is the "operator action wrapper" — the real DRY win, since the seven View pages otherwise duplicate the same five-action wiring.
3. **`OperatorConsoleCreateRecord`** (base `Filament\Resources\Pages\CreateRecord`): `$canCreateAnother = false`; a `handleRecordCreation` template that calls a per-entity `createViaAction(array $data): Model` and wraps it in the **create-rejection → form-error** catch for the common case (a localized domain `RuntimeException`, message reused on `data.<field>`).
4. **`OperatorConsoleViewRecord`** (base `Filament\Resources\Pages\ViewRecord`): uses the wrapper trait and provides the **five uniform** lifecycle header actions from per-entity Action-class references.

### How per-entity divergence is handled (and why it is small)

The console's contract — *route to the domain Action, surface whatever it throws* — means most divergences need **no per-entity console branching**; they are domain rejections the shared wrapper surfaces uniformly:

- **Activation-cascade gate** (Variant←Master; Reference←Variant+Format; Sellable←Reference+Case Config; Composite←every constituent) and **retire reference-integrity block** (Reference by active Sellable/Composite SKU; Case Configuration by active Sellable SKU) are surfaced **for free** — the Activate/Retire Actions throw a localized `gate.parent_not_active` / `retirement.blocked_by_active_references` `RuntimeException` that the wrapper renders. The four entities without a gate simply never trigger it.
- **SoD floor** is likewise surfaced (the activate Action throws a localized `approval.*` rejection); the wrapper renders it; the affordance copy is the activate action's confirmation modal.

The genuinely per-entity code is therefore minimal and explicit:

- **Create form + field→Action mapping** — per-entity `form()` + `createViaAction()`: scalar (Format, Case Configuration), single parent + wine attrs (Variant), two-parent identity (Product Reference), two-parent + commercial (Sellable SKU), N≥2 ordered constituents (Composite SKU).
- **Create-rejection catch** — the base handles the localized-domain-exception case (Composite's `InsufficientCompositeConstituents`, Master's dedup). **Product Reference is the one special case**: its duplicate `(variant, format)` is a structural DB `UniqueConstraintViolationException` with **no** localized domain message, so its Create page extends the base catch to map that framework exception to a console-owned message. (`UniqueConstraintViolationException` is a framework class — importing it does not touch the `{Models, Actions}` cross-module carve-out.)
- **Master-only extensions** — the **producer picker** (a create-form Select sourced from Catalog's own `catalog_producer_states` projection) and the **cascade-retire** header action (`RetireProductMasterCascade`). The base has no producer concept and provides only single-entity retire; Master's Resource/View page add these two. (The Producer-activation **gate** itself needs no special base support — like the cascade gate, it is a domain rejection the wrapper surfaces.)

### Retrofit Product Master onto the kit (decided: yes)

Master is **rebuilt as "kit + producer-picker extension + cascade-retire extension"** rather than left as a bespoke console. Reasons: (a) a bespoke Master alongside six kit-based siblings is exactly the special case L9 flagged; (b) retrofitting the **richest** entity is the real test that the kit is general — if the base cannot express Master's producer picker, cascade-retire and dedup, the base is wrong, and the shipped Master test suite + the arch/boundary tests are the regression net that catches it immediately; (c) one proven pattern for all seven means the Parties and A–E consoles have a single template. The retrofit changes **no** Master behaviour and **no** spec requirement about Master.

## Context

- Builds directly on [[2026-06-19-operator-console-read-binding-write-through-actions]] (the read-bind / write-through-actions discipline, the `NoEloquentWriteInOperatorPanelRule` PHPStan rule, the `ModuleBoundariesTest` `{Models, Actions}` carve-out) — that ADR settled *how a console reads and writes*; this one settles *how the consoles are structured so the pattern scales to nine modules without duplication*.
- The shipped Master console is concrete and idiomatic with **no shared scaffolding** (verified: everything inlined in `ProductMasterResource` + its three pages; `surfaceLifecycleOutcome` a private static on `ViewProductMaster`) — exactly the state L9 prescribed, and exactly the raw material to extract from.
- The six spine backends ship the same FSM and the same `Create/Submit/Reject/Activate/Retire/Reopen` Action set (only Master adds `RetireProductMasterCascade` and the Producer gate), so the common shape is real and the divergences are enumerable.

## Alternatives considered

- **Keep each console concrete (no kit), copy-paste the Master shape six times.** Rejected: seven near-identical Resources + 21 page classes + seven duplicated `surfaceLifecycleOutcome` methods is the duplication L9 only deferred, not blessed; and it leaves nine future module consoles with no template. The repetition is now evidenced enough to extract safely.
- **Extract the kit but leave Master bespoke** (build the kit for the six, don't retrofit). Rejected: it keeps one special case forever, and — worse — it designs the base against the six *simpler* entities (none of which has a producer picker, cascade-retire, or cross-table dedup), risking a base that cannot express the hardest entity, discovered late. Retrofitting Master front-loads that risk where the regression net is strongest.
- **A heavyweight generator / config-driven "resource from a schema" abstraction.** Rejected: over-engineering (the no-premature-abstraction discipline cuts both ways). The kit is plain Filament base classes + a trait; per-entity resources stay readable, idiomatic Filament. No new dependency.
- **Make the kit a cross-module/framework package.** Rejected: it is OperatorPanel-internal; it belongs under `app/Modules/OperatorPanel/Filament/`, consistent with the modular-monolith "everything under `app/Modules/{Module}/`" rule.

## Reasoning

1. **The abstraction is now earned, not guessed** — L9's precondition ("emerges from the second entity") is met by six second entities sharing one verified shape.
2. **The divergences are small and mostly free** — because the console surfaces domain rejections rather than reimplementing rules, the gate / dedup / reference-integrity differences need almost no per-entity console code; the kit's seams (create-form mapping, the one PR DB-violation catch, Master's two extras) are few and explicit.
3. **Retrofitting the richest entity proves generality** — Master's existing green suite makes the retrofit a safe, self-checking proof that the kit covers everything.
4. **It compounds** — one proven template for the Parties and A/D/S/B/C/E consoles still to come; the seam every later console plugs into.

## Trade-offs accepted

- **Churns shipped, green Master code** — the retrofit edits `ProductMasterResource` and its pages. Mitigated by the shipped Master test suite + the arch/boundary tests as a regression net, and by changing no behaviour.
- **A base class adds one indirection** — reading a per-entity Resource now means also reading the base. Accepted: the base is small and the per-entity files become correspondingly thinner; the net reading cost across seven consoles drops.
- **The kit's seams must anticipate the nine-module future without over-fitting** — it is shaped from Catalog's seven entities, and a later module (e.g. K's holds, or E's finance ops) may need a seam the kit lacks. Accepted: the kit is extended (guarded) when that lands, exactly as the boundary carve-out is; it is a starting template, not a frozen framework.

## References

- ADRs: [[2026-06-19-operator-console-read-binding-write-through-actions]] (the console's read/write discipline this builds on), [[2026-06-11-modular-monolith-architecture]] (Invariant 10; OperatorPanel = module #9; everything under `app/Modules/{Module}/`), [[2026-06-16-catalog-retirement-reference-integrity-scope]] (the within-catalog terminal-sellable-edge retire block the wrapper surfaces), [[2026-06-11-stack-versions-and-filament-ai-tooling]] (Filament 5.x).
- Predecessor change: `openspec/changes/archive/2026-06-20-operator-console-catalog-master/` — design **L9** (the deferral this resolves), the `## Codebase Patterns` (the verified Filament 5 read-only-resource / write-through-create / lifecycle-action / sink-anchored-i18n shapes the kit generalises), `proposal.md` slice-boundary (this change named as the spine successor).
- Code (the raw material to extract from): `app/Modules/OperatorPanel/Filament/Resources/Catalog/ProductMasterResource.php` + `…/Pages/{CreateProductMaster,ListProductMasters,ViewProductMaster}.php` (`surfaceLifecycleOutcome` the wrapper promotes; `producerOptions/producerLabel` the Master-only producer picker); the six entities' `app/Modules/Catalog/Actions/{Create,Submit*ForReview,Reject*Review,Activate,Retire,Reopen}<Entity>.php` (the write-through targets); `app/Modules/Catalog/Actions/CreateProductReference.php` (the DB-`UniqueConstraintViolationException` special case) and `CreateCompositeSku.php` (`InsufficientCompositeConstituents`); `tests/PHPStan/Rules/NoEloquentWriteInOperatorPanelRule.php` + `tests/Architecture/ModuleBoundariesTest.php` (the guards the kit must keep green); `lang/{en,it}/operator_console.php` + `lang/en/catalog.php` (copy).
- Spec: `openspec/specs/operator-console/spec.md` (the capability the spine change deltas into); `openspec/specs/product-catalog/spec.md` (the seven-entity lifecycle the consoles operate); `spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md` §1.2/§3.0/§5.2; `spec/02-prd/Architecture_v0.3-MVP.md` §2.3 ("owns no entities — operates the modules' entities").
- CONTEXT.md → Identity & Access (**Operator console**).
