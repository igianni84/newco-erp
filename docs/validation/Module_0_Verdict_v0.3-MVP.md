# Module 0 (PIM / Catalog) — Verdict Report vs v0.3-MVP Acceptance

> **What this is.** The Paolo-style verdict report Paolo asked Taha for (mail 2026-07-01, "Validating & closing a module"), applied to **our** underwater AI-native build. Each acceptance criterion is handed back with **Verdict · Evidence · Notes**.
> **Baseline built-against (ask #3).** Our `spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md` — a frozen snapshot of the c-mless `handoff/` at **MVP-DEC-007**. The **Canon Overlay** section flags where the current canon (through **DEC-023**) has moved.
> **Method.** AUTO criteria mapped to real code + the Pest suite (`388/388` Catalog tests pass, 2904 assertions); evidence cited as `file:line` / `Test::name`. "Deferred" = the criterion's own note defers it to a downstream module (S/A/B/C/D) not yet built, or to the LWIN/Liv-ex adapter (§0.1) — Paolo said these won't hold the module. Sample-verified by the reviewer (bulk-import absence, SoD enforcement, reject-gate, `EnrichmentDataUpdated` absence all re-checked against source).
> **Date.** 2026-07-01.

## Summary — where Module 0 stands (vs local baseline)

| Verdict | Count | % of 100 |
|---|---|---|
| **Pass** | 67 | 67% |
| **Partial** | 10 | 10% |
| **Fail / Gap** | 7 | 7% |
| **Deferred** (downstream/adapter, expected) | 16 | 16% |
| **Total** | 100 | |

**In one line:** the PIM spine is a **mature, deeply-tested implementation** — seven-entity FSM, Creator→Reviewer→Approver separation-of-duties (race-safe, admin-configurable role-count), the Producer activation gate (event-sourced from Module K), the within-module activation cascade, operator-driven cascade retirement + reference-integrity gate, and 21 of the 22 spec events on the transactional-outbox substrate. The gaps are **build-completeness holes**, not design faults: **bulk import** (5 criteria, zero code), the **Layer-1 case-configuration whitelist**, the **`EnrichmentDataUpdated`** event, **re-versioning on identity edit**, and producer-existence validation at creation.

## Ask #1 — Verdict table (against local v0.3-MVP baseline)

| AC ID | Verdict | Evidence | Notes |
|---|---|---|---|
| AC-0-J-1 | Deferred | doc §0.1 row note | LWIN-adapter Master creation; "verified when the WINE/LWIN adapter lands". Manual equiv = J-3 (Pass). |
| AC-0-J-2 | Pass | `Catalog/Lifecycle/ProducerActivationGate.php:42`; `ProductMasterLifecycleTest.php:439` | Launch-critical producer-gate + save-as-draft-interim. LWIN name-match half deferred. |
| AC-0-J-3 | Pass | `Catalog/Actions/CreateProductMaster.php:56-76`; `ProductMasterTest.php` (dup identity rejected) | Manual baseline creation + dedup end-to-end (launch-blocking enrichment path). |
| AC-0-J-4 | Pass | `ActivationCascadeTest.php`; `CatalogLifecycleChainTest.php` | Distinct actors + parent-before-child event order. |
| AC-0-J-5 | Partial | `CompositeSkuLifecycleTest.php`; `CompositeSkuTest.php:192` | Create N≥2 + all-constituent-active gate done; **immutable-once-referenced-by-active-Offer deferred to Module S** (no Offer entity). |
| AC-0-J-6 | **Fail/Gap** | grep: no Importer/CSV in `app/` | Bulk CSV/Excel import not implemented. Launch-scope canonical journey. |
| AC-0-J-7 | Partial | `Catalog/Lifecycle/LifecycleTransition.php:189-214`; `ProductMasterLifecycleTest.php:305-387` | Stays `reviewed` + notes + append-only history ✔. **No re-submit path / no review-freshness re-arm.** See Probe 1 + Canon Overlay (DEC-019). |
| AC-0-J-8 | Pass | `Actions/RetireProductMasterCascade.php`; `RetirementCascadeTest.php` | Parent-before-child retirement + block-new-under-retired. In-flight continues → Modules A+C. |
| AC-0-J-9 | Pass | `Catalog/Lifecycle/RetirementReferenceIntegrityGate.php`; `RetirementCascadeTest.php` | PIM-side open-reference block proven; voucher referencers → S+C. |
| AC-0-J-10 | Pass | `ProductMasterLifecycleTest.php:560-611` | Re-activation re-checks the Producer gate. |
| AC-0-J-11 | Deferred | HUMAN demo; console surface built (`SpineConsoleChainTest`, `ProductMasterConsoleChainTest`) | Paolo live session pending; not a code gap. |
| AC-0-J-12 | Deferred | §0.1; manual half tested (`ProductVariantLifecycleTest`) | LWIN vintage auto-populate deferred; manual Variant creation + lifecycle + events Pass. |
| AC-0-J-13 | **Fail/Gap** | migration `..product_variants` has no whitelist field | Layer-1 possible-case-configurations whitelist reduction not implemented (PIM-side is launch-testable → real gap). |
| AC-0-FSM-1..7 | Pass | per-entity `*LifecycleTest` + `Unit/Catalog/Events/*LifecycleEventsTest` | All 7 entities traverse draft→reviewed→active→retired with Created/Activated/Retired. |
| AC-0-FSM-5 | Pass | distinct `Catalog/Events/CompositeSKU*` vs `SellableSKU*` classes | Distinct event family. See Probe 2. |
| AC-0-FSM-8 | Pass | `ProductMasterLifecycleTest.php:74-76`; `LifecycleTransitionType.php:75-81` | draft→reviewed audit-only, no cross-module event. |
| AC-0-FSM-9 | Pass | `Actions/ReopenProductMaster.php` | retired→reviewed→active. |
| AC-0-FSM-10 | Pass | `Catalog/Lifecycle/ActivationCascadeGate.php`; `ActivationCascadeTest.php` | All 6 parent-child pairs. |
| AC-0-FSM-11 | Pass | `RetirementCascadeTest.php` | |
| AC-0-FSM-12 | Pass | `Catalog/Lifecycle/ProducerActivationGate.php:42-49`; `ProductMasterLifecycleTest.php:405-478` | PIM-side gate. **KYC 4-value granularity is Module K's** (Catalog projection carries active/retired only). |
| AC-0-FSM-13 | Pass | `ProductMasterLifecycleTest.php:525-558` | Block-new, never cascade-retire. |
| AC-0-FSM-14 | **Fail/Gap** | no bulk import | Depends on absent bulk-import feature. |
| AC-0-BR-Identity-1 | Pass | `CreateProductMaster.php:66-76`; `ProductMasterTest.php` | Manual dedup Pass; adapter path deferred. |
| AC-0-BR-Identity-2 | Pass | migration single-FK; `ProductVariantTest.php` | |
| AC-0-BR-Identity-3 | Pass | `Models/ProductReference.php:22-27`; migration `..references` (no `case_configuration_id`) | Allocations-at-PR-level → Module A. |
| AC-0-BR-Identity-4 | Deferred | migration `000007` docblock; no Update action | Composition-immutable-once-referenced → all referencers downstream (A/B/S). |
| AC-0-BR-Lifecycle-1 | Pass | `Catalog/Lifecycle/ApprovalGovernance.php:110-134`; `ProductMasterLifecycleTest.php:234-303` | Distinct-actor / no self-approval enforced + tested. See Probe 3. |
| AC-0-BR-Lifecycle-2..5 | Pass | covered by FSM-1..7/9/10/11 + J-9 | |
| AC-0-BR-Lifecycle-6 | Partial | same as J-7 | Review-freshness / re-submit not enforced. See Canon Overlay (DEC-019). |
| AC-0-BR-Audit-1 | Partial | `version` col (default 1); **no Update action, no Edit page** | Append-only audit ✔; **no identity-edit → new-version → deprecate-old** path. Launch-scope §4.3. |
| AC-0-BR-Audit-2 | Deferred | row note | Enrichment-never-priced → Modules S+A. |
| AC-0-BR-Audit-3 | Deferred | §0.1 | Capture-then-own = adapter-path. |
| AC-0-BR-Producer-1..2 | Pass | covered by FSM-12/13 | |
| AC-0-BR-SKU-1 | Pass | migration `..sellable_skus`; `SellableSkuLifecycleTest` | |
| AC-0-BR-SKU-2 | Pass | `CreateCompositeSku.php:60-89`; `CompositeSkuLifecycleTest` | PIM governs, doesn't validate Module-S logic. |
| AC-0-BR-SKU-3 | Deferred | row note | Atomic-at-sale → Modules A+B+C. |
| AC-0-BR-SKU-4 | Deferred | same as J-5 | Immutable-once-referenced → Module S. |
| AC-0-BR-SKU-5 | Pass | `CompositeSkuTest.php` (producer-agnostic constituents) | |
| AC-0-BR-RefData-1 | Pass | `FormatLifecycleTest`; `ActivationCascadeTest` (PR→Format gate) | |
| AC-0-BR-RefData-2 | Pass | migration `..case_configurations` (no breakability); `CaseConfigurationTest` | |
| AC-0-BR-BulkImport-1 | **Fail/Gap** | no bulk import | Configurable-depth import absent. |
| AC-0-BR-BulkImport-2 | **Fail/Gap** | no bulk import | Error-log/skip-invalid absent. |
| AC-0-BR-BulkImport-3 | **Fail/Gap** | no bulk import | All-enter-draft/no-batch-approval absent. |
| AC-0-BR-BulkImport-4 | Partial | `CatalogServiceProvider.php:44-45`; `ProducerLifecycleProjector.php:33-37` | No-auto-replay structurally holds, but the feature it governs is absent. |
| AC-0-BR-Resilience-1 | Partial | `CreateProductMaster.php` (manual = only path) | Manual-fallback present; retry-before-fallback deferred (no adapter). |
| AC-0-BR-Contract-1 | Pass | `Platform/Events/DomainEvent.php:27,55`; `DomainEventsSchemaTest.php:51-59` | PIM-side version field; downstream-consume deferred. |
| AC-0-EVT-1..7 | Pass | `Unit/Catalog/Events/*LifecycleEventsTest` | 21 of 22 events present + PII-free. |
| AC-0-EVT-8 | **Fail/Gap** | grep "Enrichment" → nothing | `EnrichmentDataUpdated` + post-active enrichment-edit path absent (the 1 missing event). |
| AC-0-EVT-9..11 | Pass | `ProductMasterLifecycleTest.php:74-76`; `ActivationCascadeTest`; `RetirementCascadeTest` | No `*Reviewed`; parent-before-child ordering. |
| AC-0-EVT-12 | Deferred | row note (Module S consumer) | Substrate order-tolerance proven (`ProducerLifecycleProjectorTest`). |
| AC-0-EVT-13 | Pass | same as BR-Contract-1 | |
| AC-0-EVT-14..19 | Deferred | row notes | Module S/A/B/C/D consumers not built. |
| AC-0-EVT-20..21 | Pass | `Catalog/Consumers/ProducerLifecycleProjector.php`; `ProducerLifecycleProjectorTest` | Consumes ProducerActivated/Retired. |
| AC-0-XM-1 | Pass | migration `..product_masters` (`producer_id`, no producer entity) | Link only. |
| AC-0-XM-2 | Partial | `CreateProductMaster.php` — no producer-existence check (bare `producer_id`, no FK) | Binding enforced at the activation gate, not creation. **Literal divergence** from XM-2. |
| AC-0-XM-3 | Pass | entity/event listing: no Bottle-Page entity on any of 7 types | |
| AC-0-XM-4 | Partial (MIXED) | `Platform/I18n/SupportedLocale.php` (6 locales); Master/Variant translatable; `SpineConsoleI18nTest` | Master+Variant ✔; **PR has no prose surface.** Paolo readability spot-check pending. |
| AC-0-XM-5..9 | Pass | schema scans across 7 tables | No price/cost/currency (5); no allocation/visibility/sub-pool (6); no serialization/NFC/NFT (7); no `is_club_package` (8); `ProductType.php` WINE-only fail-closed (9). |
| AC-0-XM-10 | Pass | covered by BR-SKU-5 | |
| AC-0-XM-11 | Partial | no-`is_breakable` half holds; **Layer-1 whitelist does not exist** | Same root as J-13. |
| AC-0-XM-12 | Pass | schema scan: no payment/settlement/GL/financial-event field | |
| AC-0-GEN-1..3 | Pass | `Enums/ProductType.php`; neutral cores + `*_wine_attributes`; `ProductMasterTest`/`ProductVariantTest` | Product Type first-class WINE-only; wine attrs off the neutral core; type-neutral variant identifier. |
| AC-0-GEN-4 | Partial | `CreateProductMaster.php` (manual = only path) | No adapter-selection seam built; adapter-path deferred (§0.1). |
| AC-0-GEN-5 | Pass | covered by BR-Identity-1 | See Canon Overlay (DEC-023). |
| AC-0-GEN-6 | Pass | `Architecture/CatalogNamingCascadeTest.php` (no Wine*/BottleReference* identifiers) | Product* names; payloads unchanged. |
| AC-0-GEN-7 | Pass | unchanged areas covered by own passing criteria | No wine-regression. |
| AC-0-GEN-8 | Pass | `ProductType.php` backed enum (not EAV); covered by XM-9 | Neutral core + additive per-type set. |
| AC-0-GEN-9 | Deferred (MIXED) | AI parity evidence assembled (`CatalogNamingCascadeTest`) | Paolo's non-behavioural-for-wine confirmation pending. |
| AC-0-GEN-10 | Pass | canonical names in models/events; `CatalogNamingCascadeTest` | Cross-module cascade verified as siblings land. |
| AC-0-GEN-11 | Pass | `CompositeSkuTest` (N≥2 + multi-producer accepted) | D7 multi-producer seam retained. |
| AC-0-GEN-12 | Pass | `config/catalog.php:28` (role_count {2,3}); `ProductMasterLifecycleTest.php:268-290` | 2-step admits, self-approval rejected at any depth. See Probe 3. |

## Canon Overlay (ask #3) — where a local Pass is still behind current canon

Module 0's canon delta is **light**: **1 new criterion + ~5 touched rows** (100 → 101 criteria).

| Canon change | DEC | Our position | Action |
|---|---|---|---|
| **`AC-0-J-7` / `BR-Lifecycle-6` — reject/edit gate made explicit + review-freshness invariant** (a pending rejection *blocks* `reviewed→active`; re-submission is an explicit audited action; editing review-governed content **re-arms review**) | DEC-019 | Already **Partial vs our own baseline** (no re-submit path, no re-arm). Canon has moved *further* in the same direction. **Double gap** on Paolo's "rejection round" scenario. | Build the explicit block-gate + review-freshness re-arm + re-submit action. |
| **`AC-0-BR-Identity-5` (NEW) — Product Type immutable post-creation** (retire + re-register, never type-edit) | DEC-023 | Not in our built-against set. Structurally we already have **no type-edit path** (WINE-only, no Update action) → we would likely **Pass** it as-is; add an explicit guard-test. | Add a criterion + a "type edit rejected" test. |
| **`AC-0-BR-Identity-1` — LWIN code now persisted as external ref, not in the dedup key** | DEC-023 | LWIN adapter is deferred at launch; our dedup key already = producer+name+appellation → **aligned**. No action at launch. | Note for when the adapter lands. |
| `AC-0-GEN-5`, `AC-0-XM-3` — minor wording | DEC-023 / — | No behavioural impact. | None. |

## Top launch-critical gaps (most severe first)

1. **Bulk import entirely missing** (J-6, FSM-14, BR-BulkImport-1/2/3) — a canonical journey + 4 rules, **zero code**. Largest scope hole.
2. **Layer-1 case-configuration whitelist missing** (J-13, XM-11) — PIM's only contribution to layered breakability, a documented Phase-C floor-chain feed, unbuilt. PIM-side is launch-testable → real gap, not a deferral.
3. **`EnrichmentDataUpdated` event + post-active enrichment path missing** (EVT-8) — 21 of 22 events. Lower severity (no consumer yet) but launch-scope, not adapter-gated.
4. **Re-versioning on identity edit missing** (BR-Audit-1) — `version` stays 1; no Update action / Edit page, so operators can't correct identity fields into a new deprecated-old/active-new version.
5. **Producer-existence not validated at creation** (XM-2) — `CreateProductMaster` accepts any `producer_id`. Defensible under the boundary law + J-2, but a literal XM-2 divergence — flag with an ADR/overlay note.

## Special probes (verified against source)

- **Probe 1 — reject/edit gate (J-7 / BR-Lifecycle-6):** stays `reviewed`, notes + append-only history at `LifecycleTransition.php:189-214` (proven `ProductMasterLifecycleTest.php:305-337`). **But a distinct approver can approve directly post-rejection with no fresh review** (`:363-387`); no re-submit action; edit-after-review does not re-arm. Confirmed absent by grep (`review.?fresh|re-?submit|re-arm` → nothing).
- **Probe 2 — SKU event families (FSM-5 / EVT-5):** distinct classes/triplets — `Events/SellableSKUCreated.php:28` vs `Events/CompositeSKUCreated.php:28,31`, dispatched by name. Case Configuration is Intrinsic-only (`CreateSellableSku.php:39-52` takes it; `CreateCompositeSku.php:51-89` does not; `case_configuration_id` on `catalog_sellable_skus` only).
- **Probe 3 — self-approval / SoD (BR-Lifecycle-1 / GEN-12):** enforced at `ApprovalGovernance.php:110-134` (`assertSeparationOfDuties`), invoked on every Activate (`LifecycleTransition.php:127-129`). Tests: creator/reviewer/system-actor cannot approve (`ProductMasterLifecycleTest.php:234-303`); role-count knob `config/catalog.php:28`. **This is Paolo's "confirm self-approval is blocked" check — Catalog passes it, through the Filament console too.**
