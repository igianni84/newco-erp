# Proposal — operator-console-catalog-master

## Why

This is the **first operator-facing UI** in the repo. Fourteen changes shipped the Module 0 (Catalog) and Module K (Parties) backends; the Filament panel shell exists (`operator-auth-foundation`: `/admin`, the `operator` guard, login + opt-in TOTP, bare RBAC roles) but has **zero resources** — the backends are not yet operable or demoable by a human. This change makes the Catalog **Product Master** lifecycle operable through the panel, and in doing so **founds the `operator-console` capability**: the read-binding / write-through-actions pattern, the `actor_role` audit envelope on operator writes, and the multi-actor (SoD) affordance — the seam every later console (the six remaining catalog spine entities, then Parties, then A/D/S/B/C/E) reuses.

- It is the **first vertical slice**, chosen to prove the pattern end-to-end on the **richest** catalog entity: Product Master exercises create-with-dedup, the full `draft → reviewed → active → retired` lifecycle with the 3-step Creator → Reviewer → Approver floor and self-approval rejection, the **Producer-activation gate** (Master-only), the operator-driven **cascade retire**, and reopen. If the pattern holds for the hardest entity, the remaining six are mechanical repetition (`operator-console-catalog-spine`).
- It implements the STEP-1 decision `decisions/2026-06-19-operator-console-read-binding-write-through-actions.md`: the OperatorPanel is the composition layer; its Filament resources bind **read-only** to `App\Modules\Catalog\Models\*`, and **every** mutation routes through the existing Catalog domain actions — never `$model->save()`. A CI architecture test enforces it.
- It introduces **no new domain logic**: zero new Actions, events, migrations, or dependencies in the Catalog backend. It is a pure operator-surface layer over shipped behaviour (Admin Panel PRD §1.2 — the surface *references* the module backend, never re-specs it). The capability it adds is `operator-console`.
- It steps through **no open ADR gate**: operator auth is decided + shipped (`2026-06-15-identity-auth`), the Filament stack is pinned (`2026-06-11-stack-versions-and-filament-ai-tooling`), and the read/write boundary is now decided (`2026-06-19-operator-console-read-binding-write-through-actions`). The consumer/producer frontend-stack gate is **not** crossed — this is the operator (Filament) surface only.

## What Changes

- **Capability foundation — `operator-console`** (ADR 2026-06-19; Architecture §2.1/§2.3; Invariant 10) — repoint `AdminPanelProvider` resource/page/widget discovery from the shell's default `app/Filament/**` to **`app/Modules/OperatorPanel/Filament/**`** (namespace `App\Modules\OperatorPanel\Filament\*`), grouped by operated module (`Resources/Catalog/…`); add the **architecture test** that fails when any class under `App\Modules\OperatorPanel\**` performs an Eloquent write (`save`/`update`/`delete`/`forceDelete`/`create`/`insert`) on a module model. These two establish the read-projection + write-through-actions discipline for every future console.
- **Product Master read surface** (Admin Panel §3.0; AC-AP-INV-0) — a `ProductMasterResource` binding **read-only** to `App\Modules\Catalog\Models\ProductMaster`: a list (name, product type, `lifecycle_state`, producer, version) and a view (incl. the wine attribute set). The producer column resolves through Catalog's **own** `catalog_producer_states` projection (`ProducerState`), never Module K. No default create/edit/delete mutating actions.
- **Create a Product Master (manual baseline path)** (Admin Panel §3.0; AC-AP-INV-0; product-catalog *Product Master*) — a create surface whose `handleRecordCreation()` invokes `CreateProductMaster` (manual baseline — the LWIN/Liv-ex enrichment adapter is not shipped); the identity-key dedup (producer + name + appellation) is surfaced as a form validation error. The new Master is `draft`; `ProductMasterCreated` records with `actor_role: newco_ops`.
- **Approval lifecycle + the SoD affordance** (Admin Panel §5.2; AC-AP-MA-1; product-catalog *Approval Governance*) — Filament Actions for submit-for-review (`SubmitProductMasterForReview`, audit-only), reject (`RejectProductMasterReview`, keeps `reviewed` + notes), and activate (`ActivateProductMaster`). The activate action surfaces the **"second actor required"** affordance; the 3-step distinct-actor floor + self-approval rejection are enforced by the domain (`ApprovalGovernance`) and surfaced as a Filament notification — the panel does not reimplement the rule.
- **Producer-activation gate surfaced** (product-catalog *Producer Activation Gate*) — activating a Master whose linked Producer is not `active` (per the projection) is rejected by the domain and surfaced clearly; activation succeeds only when the producer is active.
- **Retire / cascade-retire / reopen** (Admin Panel §3.0; product-catalog *Retirement Cascade and Reference Integrity*; ADR 2026-06-16) — Actions for `RetireProductMaster` (single-entity, preserves active children), the operator-driven `RetireProductMasterCascade` (Master + active descendants, parent-before-child), and `ReopenProductMaster` (`retired → reviewed`, re-checks the gate on next activate).
- **Every console write carries the audit envelope** (Admin Panel §1.3; AC-AP-PWB-6) — because writes route through the domain actions, each records `actor_role: newco_ops` + the operator id + timestamp + action + entity ref via the existing `ActorContext` seam. No console-specific envelope code.
- **i18n EN + IT** (Architecture §5.1; Invariant 12) — all operator-facing copy through Laravel localization; reuse `lang/{en,it}/catalog.php` for the domain rejection messages and add the console UI copy keys; EN baseline + per-key IT fallback.

### Slice boundary — deliberately NOT in this change

| Deferred concern | Future change | Why not here |
|---|---|---|
| The **other six catalog spine entities** consoles (Variant, Product Reference, Format, Case Configuration, Sellable SKU, Composite SKU) + §3.0 *Format/Case Configuration governance* | **`operator-console-catalog-spine`** | This slice proves the pattern on Product Master; the rest is mechanical reuse. Keeping it out holds the change to one ralph-sized slice. |
| **Bulk import** (§3.0 cap 4) | pending a Catalog backend change | **No backend exists** — `product-catalog` spec does not specify bulk import and no Action exists. Not operable; flagged, not silently dropped. |
| **Enrichment-metadata update** (§3.0 cap 6) | pending a Catalog backend change | No update Action exists; the spec marks enrichment *"observational, not in launch"*; `EnrichmentDataUpdated` is unemitted. |
| **Field-editing a Master in place** | pending a verified Catalog update Action | The shipped Catalog backend has **no** update Action (43 actions: create/submit/reject/activate/retire/reopen/cascade only). The console offers lifecycle transitions, not field edits. |
| **Authority-tier RBAC / persona-gating** (which role runs which capability) | **`feedback_prd_rr_approval`** | Role-agnostic at this layer (Admin Panel §1.4). The only floor preserved is the spec-mandated SoD (enforced in the domain). |
| **Parties console** | **change 2 (`operator-console-parties-*`)** | Different module; reuses this slice's pattern. |
| **Producer / Consumer portals** (TanStack SPA) | the Module S storefront gate | Operator (Filament) surface only; the SPA frontend ADR is unwritten. |
| **UX / IA / layout polish** | roadmap (#12) | The PRD is capability-not-layout (DEC-073). |

## Capabilities

### New Capabilities

- `operator-console`: the operator-facing Admin Panel surface that operates the modules' entities (owns none of its own — ADR 2026-06-19; Architecture §2.3). This change seeds it with the read-projection / write-through-actions discipline, the `actor_role: newco_ops` audit envelope on operator writes, the multi-actor (SoD) affordance, and the **Product Master** console (the first vertical slice). Future changes extend the same capability with the remaining catalog spine, then the Parties console, then the cross-module consoles.

### Modified Capabilities

_None._ The Catalog backend (`product-catalog`) is **not** modified — this surface only reads its models and invokes its existing actions (Admin Panel §1.2). No existing capability's requirements change.

## Impact

- **New code** — `app/Modules/OperatorPanel/Filament/Resources/Catalog/ProductMasterResource.php` + its List/View/Create pages + the lifecycle Action classes; the architecture-enforcement test (a PHPStan custom rule, or a Pest token-scan test if simpler); console i18n keys in `lang/{en,it}/` (a new `operator_console` group + reuse of `catalog`); Pest resource/page tests under `tests/Feature/Modules/OperatorPanel/`.
- **Modified code** — `app/Modules/OperatorPanel/Providers/AdminPanelProvider.php` (discovery repointed to `app/Modules/OperatorPanel/Filament/**`; app code, not protected); possibly `phpstan.neon` (register the custom rule).
- **No migration, no new dependency** — `git diff main -- composer.json composer.lock` stays empty; no schema change (nothing is written to the DB that does not route through a shipped Action).
- **Reuses, does not modify** — the Catalog domain Actions (`CreateProductMaster`, `SubmitProductMasterForReview`, `RejectProductMasterReview`, `ActivateProductMaster`, `RetireProductMaster`, `ReopenProductMaster`, `RetireProductMasterCascade`); `LifecycleTransition` + its gates (`ApprovalGovernance`, `ProducerActivationGate`); the `ProductMaster` + `ProducerState` models (read-binding); `ActorContext` / `ActorRole`; the `operator` guard + `Operator` principal.
- **Boundary & arch** — the OperatorPanel reads `App\Modules\Catalog\Models\*` read-only (the sanctioned ADR-2026-06-19 exception) and writes only through Catalog actions; the architecture test enforces no Eloquent write in the OperatorPanel namespace; module models carry no cross-module relations, so Filament eager-loads cannot cross a boundary; derived cross-module status (none needed for the Master console) would use `PartyComplianceStatusReader`.
- **DB engines** — every test that drives a domain action touches the DB, so the capability-close task verifies the suite on **PostgreSQL 17** as well as SQLite (`knowledge/testing/rules.md`).
- **Dependency note** — creating/activating a Master needs a Producer in Catalog's projection (and `active`, for the gate); until the Parties console (change 2) ships, producers are seeded — and the gate-blocked path is itself a required test scenario.
- **Deliberate traceability gaps** — bulk import, enrichment-metadata update, and field-editing have **no shipped backend** and are recorded as named deferrals above (not silent cuts); the remaining spine + Parties consoles are named future changes.
