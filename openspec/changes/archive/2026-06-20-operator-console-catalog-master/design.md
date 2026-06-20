# Design — operator-console-catalog-master

## Context

The repo is backend-only: 14 archived changes, the Module 0 (Catalog) and Module K (Parties) backends, and a Filament panel **shell** from `operator-auth-foundation` (`/admin`, the `operator` session guard, login + opt-in TOTP, `spatie` roles seeded bare) — but **zero Filament resources**. This change builds the first console: the Catalog **Product Master** lifecycle, and the `operator-console` capability foundation it rides on.

It is governed by `decisions/2026-06-19-operator-console-read-binding-write-through-actions.md` (the STEP-1 ADR): the OperatorPanel is the composition layer; Filament resources read-bind to module Eloquent models read-only, every write routes through the module's domain actions, and a CI architecture test enforces it. The Catalog backend is **not** modified — this is a pure operator-surface layer over shipped behaviour (Admin Panel PRD §1.2).

The Catalog backend it operates (verified in code): `ProductMaster` (`catalog_product_masters`, `lifecycle_state` cast) with 1:1 wine attributes (`appellation`, `region`, `winery_story`); the four-state FSM `draft → reviewed → active → retired` driven by explicit Actions through `LifecycleTransition`; `ApprovalGovernance` (distinct-actor floor) and `ProducerActivationGate` (Master-only, reads `catalog_producer_states` / `ProducerState`); the Action set `CreateProductMaster`, `SubmitProductMasterForReview`, `RejectProductMasterReview`, `ActivateProductMaster`, `RetireProductMaster`, `ReopenProductMaster`, `RetireProductMasterCascade`. There is **no** update Action and **no** bulk-import Action.

## Goals / Non-Goals

**Goals:**
- Found the `operator-console` capability: discovery repointed to `app/Modules/OperatorPanel/Filament/**`; the architecture test; the read-projection + write-through-actions discipline; the `actor_role: newco_ops` envelope on operator writes; the multi-actor (SoD) affordance.
- A complete, demoable **Product Master** console: create (manual baseline + dedup), submit/reject/activate (with the SoD affordance + Producer gate surfaced), retire/cascade-retire/reopen, list + view — all read-binding for display, all writes through Catalog actions.
- EN + IT localization, no hardcoded copy.
- Green on SQLite **and** PostgreSQL 17; Pint + PHPStan green.

**Non-Goals:**
- The other six catalog spine entities (→ `operator-console-catalog-spine`); the Parties console (→ change 2).
- **Bulk import** and **enrichment-metadata update** — no shipped backend (deferred-pending-backend, proposal slice-boundary table).
- **Field-editing a Master in place** — no shipped update Action; the console offers lifecycle transitions, not field edits.
- Authority-tier RBAC / persona-gating (→ `feedback_prd_rr_approval`); the only floor preserved is the spec-mandated SoD, enforced in the domain.
- UX / IA / layout polish (capability-not-layout, DEC-073); Producer/Consumer SPA portals.
- No premature shared abstraction — see L9.

## Decisions

**L1 — Resources read-bind to Catalog models, read-only.** `ProductMasterResource` sets `$model = \App\Modules\Catalog\Models\ProductMaster::class` and uses native Filament tables/infolists over that model's query (the ADR-sanctioned exception, OperatorPanel only, read/display only). Alternative — a per-entity DTO read contract — rejected (fights Filament; PII-free DTOs are wrong for an operator surface; ADR Alternatives). Module models have no cross-module Eloquent relations, so eager-loading cannot cross a boundary. The repo's **import-boundary test** (the `ModuleBoundariesTest` / arch test the `2026-06-15-auth-principal-table-naming` ADR calls "the import-boundary test") forbids cross-module model imports and will trip on `App\Modules\OperatorPanel` importing `App\Modules\Catalog\Models\*`; it MUST be amended to encode the ADR exception — OperatorPanel may import module `Models\*` **for reads** — while the no-Eloquent-write rule (L4) keeps the write discipline. Verify what that test actually asserts before editing it; narrow the carve-out to the OperatorPanel namespace only (lateral business-module model imports stay forbidden — plant one to prove it still fails).

**L2 — Writes through domain actions; no Edit page.** Lifecycle operations are **Filament Actions** (header/row actions on the resource and view page) that call `app(<Action>::class)->handle(...)`. Create is a Filament **Create page** whose `handleRecordCreation(array $data): Model` calls `app(CreateProductMaster::class)->handle(...)` and returns the new `ProductMaster` (Filament accepts the returned model — verify the v5 signature in `vendor/` before writing). There is **no Edit page** and no `DeleteAction`: the Catalog backend ships no update Action, so post-creation field edits are out of scope (proposal slice-boundary). The create form collects name / producer / appellation / region / winery_story only.

**L3 — Resource location + discovery repoint.** Resources live at `app/Modules/OperatorPanel/Filament/Resources/Catalog/`, pages at `.../Pages/`, namespace `App\Modules\OperatorPanel\Filament\Resources\Catalog\…`. `AdminPanelProvider::panel()` `discoverResources/discoverPages/discoverWidgets` are repointed from `app_path('Filament/...')` to the module path with the matching `for:` namespace. `AdminPanelProvider` is app code (not protected). The old `app/Filament/**` path had no resources, so the repoint is backward-safe.

**L4 — The architecture test.** Primary: a **PHPStan custom rule** (Larastan is in the stack — `type_check` = `vendor/bin/phpstan analyse`) that flags a `MethodCall`/`StaticCall` whose method ∈ {`save`,`saveQuietly`,`update`,`updateQuietly`,`delete`,`forceDelete`,`create`,`insert`,`fill`,`setAttribute`} on an Eloquent-model type, in a file under `app/Modules/OperatorPanel/`, registered in `phpstan.neon`. Acceptable fallback if the rule proves heavy: a **Pest test** that tokenises every `.php` under `app/Modules/OperatorPanel/Filament/` and asserts none of those write calls appear on a `*Model` receiver. Either way it MUST be proven by a planted violation (red) then removal (green). Reads (binding `$model`, `Model::query()` for display) are allowed.

**L5 — SoD affordance: surface, don't reimplement.** `ApprovalGovernance` inside `LifecycleTransition` already enforces distinct actors / no self-approval and throws on violation. The console (a) shows a visible **"requires a different operator than the previous step"** affordance on the activate action, and (b) wraps the `app(ActivateProductMaster)` call so the domain exception renders as a Filament **danger notification** — the Master is unchanged. Pre-disabling the action for the prior-step actor (reading the audit trail) is an acceptable enhancement but **not required** for AC-AP-MA-1; the label + domain-enforced rejection satisfy it.

**L6 — Producer gate + producer picker.** The create form's producer field is a select populated from Catalog's **own** `ProducerState` projection (`catalog_producer_states`) — read-only, within Catalog, never Module K. The activate action wraps `app(ActivateProductMaster)`; `ProducerActivationGate` rejection renders as a notification ("the linked producer is not active"). Showing only `active` producers in any "activatable" affordance is a nicety; the authority is the domain gate.

**L7 — Cascade retire is a distinct affordance.** Single-entity `RetireProductMaster` and the operator-driven `RetireProductMasterCascade` are **separate** actions with distinct labels (the cascade warns it retires active descendants). `ReopenProductMaster` is the `retired → reviewed` action. The console triggers the domain action; ordering/preservation are the domain's (verified behaviour).

**L8 — i18n.** Console UI copy goes in a new `lang/{en,it}/operator_console.php` group (labels, action names, notifications); domain rejection copy already lives in `lang/{en,it}/catalog.php` and is reused where the action's exception message is shown. EN baseline + per-key IT fallback (DEC-127). No hardcoded user-facing string in any Filament class.

**L9 — No premature abstraction.** With only Product Master in this change, do **not** extract a shared base Resource or an "operator action" wrapper. The right abstraction emerges from the second entity (`operator-console-catalog-spine`); extracting now would guess. Keep `ProductMasterResource` concrete and idiomatic.

**L10 — Stack + tests.** Filament 5.x / Livewire v4 (verify component test helpers in `vendor/` before writing). Tests are Pest under `tests/Feature/Modules/OperatorPanel/`, driving pages via Livewire/Filament testing helpers and `actingAs($operator, 'operator')`. Assert domain effects (state + recorded events + `actor_role`) rather than only UI — the UI is the trigger, the domain action is the contract.

## Risks / Trade-offs

- **Filament default mutating paths silently bypass the actions** → L2 (no Edit page, no `DeleteAction`, `handleRecordCreation` routes to the action) + L4 (the architecture test fails on any Eloquent write in the namespace). The test is part of DoD.
- **No Parties console yet → no producers to pick/activate** → tests seed `ProducerState` rows (and, where a real Producer is needed, seed via the Parties Actions); the **gate-blocked** path (producer not active) is itself a required scenario, so the dependency is exercised, not blocked. A small demo seeder is optional.
- **Architecture test gives false confidence if too coarse** → detect at token/AST level on the specific write methods + model receiver; prove with a planted violation. A pure grep on `->save(` risks false positives (e.g. a form-state call) — scope to model receivers.
- **SoD double-implementation drift** → L5: the console never re-checks actor identity; it only surfaces the domain's decision. One source of truth (`ApprovalGovernance`).
- **`handleRecordCreation` return contract** → verify the Filament 5 `CreateRecord::handleRecordCreation(array $data): Model` signature in `vendor/` before relying on it; the action returns the model, which is what Filament expects.

## Migration Plan

No database migration. Deploy is code-only. Rollback = revert the change (the discovery repoint is backward-safe — no resources existed at the old path). No data backfill, no feature flag.

## Open Questions

- **Demo seeding of producers** until the Parties console ships — tests self-seed `ProducerState`; whether to add a dev seeder for the human demo is a convenience, resolved during implementation (not a blocker).
- **Affordance richness** for the second-actor requirement (label + rejection vs pre-disabling the prior actor) — this slice ships the label + domain rejection (satisfies AC-AP-MA-1); pre-disabling is a candidate enhancement for the spine change once the pattern is shared.
