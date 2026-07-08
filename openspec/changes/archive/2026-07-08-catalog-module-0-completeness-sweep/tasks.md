# Tasks — catalog-module-0-completeness-sweep

> One task per loop iteration. Tests are NEVER optional. Full-suite runs via `php -d memory_limit=-1 vendor/bin/pest`; type check `php -d memory_limit=-1 vendor/bin/phpstan analyse`; format `vendor/bin/pint`. Reference: `design.md` D1–D11 + R1–R12.

## 1. Substrate — whitelist schema + review-freshness filter + edit mechanic

- [x] 1.1 Create the Layer-1 whitelist pivot: migration `catalog_variant_case_whitelists` + `VariantCaseWhitelistEntry` model + within-module relations (D6)
  - Columns: `id`, `product_variant_id` FK cascade, `format_id` FK restrict, `case_configuration_id` FK restrict, `timestampsTz`; unique on the triple; every FK/index/unique explicitly named with a `catalog_vcw_*` prefix (<63 bytes)
  - Model casts the three ids to integer; Variant exposes a within-module relation to its whitelist entries (architecture rule — no boundary amendment needed)
  - Tests: schema (columns, unique triple rejects a duplicate on both engines, FK restrict on format/case-config delete), and the XM-11 absence half — assert no `is_breakable`/`breakable` column exists on the new table (grep-style schema assertion mirroring `CaseConfigurationTest`)
  - Typecheck passes; tests pass

- [x] 1.2 Refactor review-freshness to the 4-suffix filtered derivation (S1) in BOTH readers: `ApprovalGovernance::assertNotRejectionPending` (rename to reflect review-stale) and the console `OperatorConsoleViewRecord::isRejectionPending` → `isReviewStale` (D4, D9, R3)
  - Domain predicate: among the entity's `audit_records` actions ending `.submitted`/`.resubmitted`/`.rejected`/`.identity_updated`, latest wins; blocked iff it ends `.rejected` or `.identity_updated`; exception message key extended for the edited-not-re-reviewed case (EN+IT)
  - Console mirror uses the same 4-suffix predicate over the platform `AuditRecord` (no `Lifecycle\*` import — R6); re-submit button visible on BOTH stale causes
  - Tests (simulate edit rows by writing audit records directly via `AuditRecorder` in a transaction — R4): post-rejection enrichment/whitelist row does NOT clear the block; identity_updated row blocks until resubmit; draft-edit row then submit → clear; uniformity test — console visibility agrees with the domain block on the same histories; existing RM-06 scenarios stay green (`CatalogReviewFreshnessUniformityTest`, `ResubmitActionsTest`)
  - `grep -rn "orderByDesc('id')->value('action')"` shows no remaining raw latest-action read over catalog audit rows
  - Typecheck passes; tests pass

- [x] 1.3 Build the shared `CatalogContentEdit` mechanic in `app/Modules/Catalog/Lifecycle/` (D3)
  - One entry point: transaction + `lockForUpdate` re-read, allowed-state guard (`draft|reviewed|active`; `retired` → localized exception), operator floor via `ApprovalGovernance::requireOperator`, applies field writes + `version` increment in the same UPDATE, records `catalog.<segment>.<verb>` audit with changed-fields before/after + version before/after (R9), records NO domain event
  - Unit/feature tests over a spine model: version 1→2, audit action string shape, retired rejected, `system` actor rejected, lock + no-write-on-reject invariants (state/audit/event untouched)
  - Typecheck passes; tests pass

## 2. RM-14 — identity edits (BR-Audit-1)

- [x] 2.1 `UpdateProductMasterIdentity` action (name, appellation, region, winery_story) on the mechanic (D1, D2)
  - Verb `identity_updated`; name/appellation change re-checks BR-Identity-1 dedup vs every OTHER non-retired Master (same join as `CreateProductMaster`, excluding self) → `DuplicateProductMasterIdentity`; region/winery_story-only edits skip the dedup query; wine-attribute writes go through the within-module relation
  - Tests: the BR-Audit-1 AUTO scenario — edit an `active` Master's name ⇒ version 1→2 (assert exact), stays `active`, audit row `catalog.product_master.identity_updated` with before+after carrying old/new name (old value retrievable from the audit row), NO domain event; dedup collision rejected with values+version unchanged; draft edit → submit → distinct-approver activate NOT blocked (D4 draft-clear case); retired/system rejected
  - Typecheck passes; tests pass

- [x] 2.2 `UpdateCompositeSkuComposition` action (ordered replace) on the mechanic (D2, D7-adjacent cascade re-assert)
  - `array_unique` + N ≥ 2 floor (reuse `InsufficientCompositeConstituents`); when the Composite is `active`, every NEW constituent PR must be `active` (localized rejection otherwise); replace = sync the constituents join preserving `position` order; audit before/after = ordered PR-id lists; version++
  - Tests: the BR-Audit-1 AUTO Composite half — replace on `active` Composite with active PRs ⇒ version++, before/after lists audited, stays `active`, no event; <2 distinct rejected; non-active constituent on an `active` Composite rejected (composition/version/audit/event unchanged); same edit on a `reviewed` Composite passes without the constituent-state check
  - Typecheck passes; tests pass

- [x] 2.3 Re-arm end-to-end proof (the deferred DEC-019 leg, delta *Approval Governance*)
  - Feature test(s) on the Master through the REAL actions: (a) submit → identity edit → distinct-approver activate BLOCKED → explicit resubmit → activate succeeds with exactly one `ProductMasterActivated`; (b) the 2-round J-7 flow now with a real edit inside each round (reject → edit → resubmit → reject → edit → resubmit → distinct approver activates), full history preserved (both rejections, both edits, both resubmits in audit)
  - Typecheck passes; tests pass

## 3. RM-12 — Layer-1 whitelist behavior (J-13, XM-11)

- [x] 3.1 `SetVariantCaseWhitelist` action — replace the (Variant, Format) pair's admitted set (D6)
  - Validates the Format and every CC id exist (FK-backed; surface a clean localized rejection for an unknown id rather than a raw constraint violation), states `draft|reviewed|active` allowed / `retired` rejected, operator floor; audit `catalog.product_variant.whitelist_updated` with pair + before/after CC-id sets; NO version change, NO domain event, NO review-freshness effect
  - Tests: replace writes the exact set (add + remove in one call); audit before/after sets asserted; version unchanged; event log untouched; a reviewed-then-whitelisted Variant still activates (no re-arm); retired/system rejected
  - Typecheck passes; tests pass

- [x] 3.2 Whitelist activation gate in `ActivateSellableSku` + the J-13 scenario (D6, R10)
  - Gate closure extension: resolve SKU → PR → (variant_id, format_id); if the pair has ≥1 whitelist row and the SKU's `case_configuration_id` is not among them → new localized exception (name it clearly, e.g. `CaseConfigurationNotWhitelisted`); zero rows ⇒ permissive; runs beside the existing PR+CC-active cascade conjuncts
  - Tests — the J-13 AUTO scenario verbatim: active Variant with whitelist {OWC6, CARTON12, loose}-style fixture; an `active` SKU on OWC6; remove OWC6 ⇒ existing SKU untouched (state/audit/events), NEW SKU on OWC6 for the same pair activates → rejected (stays `reviewed`, no `SellableSKUActivated`); empty-pair permissive; a whitelisted CC still activates; different-format pair unaffected (per-pair scoping proven)
  - Typecheck passes; tests pass

## 4. RM-13 — EnrichmentDataUpdated + enrichment edit (EVT-8)

- [x] 4.1 `EnrichmentDataUpdated` event class + `UpdateProductVariantEnrichment` action (D11, D2)
  - Event: `final class`, `NAME='EnrichmentDataUpdated'`, `ENTITY_TYPE='ProductVariant'`, payload `{product_variant_id}` (PII-free); action on the mechanic BUT with enrichment semantics: verb `enrichment_updated`, NO version change, fires the event in the same transaction ONLY when the stored `tasting_notes` actually changes; identical value ⇒ silent no-op (no event, no audit, no write); field-agnostic internal shape (a changed-fields map) so adapter columns join later
  - Tests: EVT-8 — change on an `active` Variant ⇒ exactly one `EnrichmentDataUpdated` (name/entity/payload asserted, PII-free) + one audit row with before/after, version unchanged, state unchanged; no-op on identical value (event+audit counts unchanged); enrichment edit on a `reviewed` never-rejected Variant does NOT block its activation (delta scenario "Enrichment never re-arms review", real-action path); retired/system rejected; the event class is glob-visible but NOT recorded by any lifecycle transition (event-log scan)
  - Typecheck passes; tests pass

## 5. RM-15 — producer existence at creation (XM-2)

- [x] 5.1 Widen the projection: `ProducerLifecycleProjector` consumes `ProducerCreated` → `ProducerProjectionStatus::Registered` (D7, R2)
  - FIRST verify the `ProducerCreated` payload key against `app/Modules/Parties/Events/ProducerCreated.php` (never invent — expect `producer_id`); register the third event-name in `CatalogServiceProvider`; enum case `Registered = 'registered'` appended; the projector's `match` maps Created→Registered with the existing watermark semantics (a stale Created after Activated is a no-op — no downgrade)
  - `EnumsTest`: `ProducerProjectionStatus` count 2→3 + value map updated; projector tests: Created ⇒ `registered` row with watermark; Created-then-Activated ⇒ `active`; REPLAYED/stale Created after Activated ⇒ still `active`, watermark unregressed; PG CHECK on `status` derives 3 tokens on fresh migrate (engine-guarded assertion per data-model rule)
  - `ProducerActivationGate` untouched and proven: a `registered` producer still blocks Master activation (extend the gate test matrix)
  - Typecheck passes; tests pass

- [x] 5.2 `CreateProductMaster` existence guard + blast-radius migration (D7, R1)
  - Guard before any write, inside the transaction: no `ProducerState` row for `producer_id` → new localized exception (e.g. `UnknownProducerReference`), no Master row, no `ProductMasterCreated`; a `registered` or `retired` row admits creation
  - BEFORE wiring: `grep -rn 'CreateProductMaster' app/ tests/ database/` + console create tests; classify every caller (real producer lineage vs bare int) and migrate fixtures (seed a `ProducerState` row or create the producer through the real action); check `DemoSeeder` (creates producers via real actions → projected inline; extend its truncation sweep to the whitelist table if it sweeps catalog tables — design Migration note)
  - Tests: unknown id rejected (no row, no event); `registered` id admitted and the Master holds in `draft` with activation still gate-blocked (ties to 5.1); FULL suite green on SQLite (the only proof the blast radius is covered — R1)
  - Typecheck passes; tests pass

## 6. Console surfaces

- [x] 6.1 `ViewProductMaster` edit-identity modal action + create-page unknown-producer mapping (D8, delta *Operator edits catalog identity content…* + *Operator creates a Product Master…*)
  - Kit modal action prefilled with current name/appellation/region/winery-story (reuse the create-form translatable-prose field pattern — R8), invoking `UpdateProductMasterIdentity`; dedup collision AND unknown-producer (create page) mapped to form validation errors (precedent: `CreateProductReference` unique-violation mapping); success notification; `version` visible on the View reflects the increment; new keys authored EN+IT (R5); update the Master resource/view "no Edit page" comment rationale (R12 partial)
  - Livewire tests: happy edit (version+1 rendered, audit envelope `newco_ops`+operator id), dedup validation error (unchanged), create-page unknown-producer validation error (no Master, no event), retired Master → action hidden or rejection surfaced (pick the kit-consistent shape and assert it)
  - Typecheck passes; tests pass

- [x] 6.2 `ViewProductVariant` edit-enrichment + manage-whitelist modal actions (D8, delta *Operator maintains Variant enrichment and the Layer-1 whitelist…*)
  - Enrichment modal → `UpdateProductVariantEnrichment` (works on `active`; success notification; version untouched); whitelist modal → choose Format + replace admitted CC set → `SetVariantCaseWhitelist` (works on `active`); both surface domain rejections via the kit; keys EN+IT; comment rationale updated
  - Livewire tests: enrichment change ⇒ exactly one `EnrichmentDataUpdated` + notification; whitelist replace ⇒ audit row with before/after sets, no event; console SKU-activation attempt against a removed CC surfaces the whitelist rejection notification (delta scenario)
  - Typecheck passes; tests pass

- [x] 6.3 `ViewCompositeSku` edit-composition modal action (D8)
  - Ordered PR multi-select prefilled with current constituents, invoking `UpdateCompositeSkuComposition`; N≥2 / non-active-constituent rejections surfaced (validation error or notification — kit-consistent); keys EN+IT; comment rationale updated
  - Livewire tests: happy replace on `active` Composite (version+1, notification); non-active constituent surfaced with state unchanged
  - Typecheck passes; tests pass

## 7. Docs + full verify

- [x] 7.1 CONTEXT.md + residual-claim sweep (R12, D5)
  - CONTEXT.md: rewrite *Producer-state projection* (three statuses incl. `registered`, fed by `ProducerCreated`/`Activated`/`Retired`; creation-existence read) and *Approval governance* (review-stale = rejection OR identity edit; the 4-suffix verb-filtered derivation; verb-collision discipline D5) entries; ADD entries *Layer-1 whitelist*, *Enrichment data*, *Identity edit (re-versioning)* with `_Avoid_` glosses
  - Sweep the inverted claims repo-wide (grep, not memory): "sole catalog audit writer" (`ApprovalGovernance`/`LifecycleTransition` docblocks), "re-versioning … deferred (RM-14)" (`LifecycleTransition.php` ~L229), "ships no update Action" (any resource comment missed by 6.x), "rejection-pending" phrasing left implying rejection-only, projection "two-status/two-event" claims (migration + consumer docblocks, `decisions/` prose if any)
  - Acceptance: each grep hit either updated or justified in progress.md; no code-behavior change in this task
  - Typecheck passes; tests pass (unchanged counts)

- [x] 7.2 Full verify + wrap
  - `php -d memory_limit=-1 vendor/bin/pest` green on SQLite AND PG17 (recipe in hot.md), PHPStan max 0, Pint clean, `openspec validate catalog-module-0-completeness-sweep --strict` green
  - Traceability check against the delta: every ADDED/MODIFIED requirement's scenarios covered by named tests (list the mapping in progress.md); deferred seams (Module A Layer-2, Module S consumer, adapter columns) named once in progress.md
  - progress.md `## Codebase Patterns` consolidated; tracker/hot/log updates are session-close work, not this task
  - Typecheck passes; tests pass


## 8. Semantic-verify remediation (2026-07-08, post-merge, pre-archive)

Three parallel verifier passes over the 12 delta requirements returned **no CRITICAL**. These four items are the WARNINGs that were confirmed against the code rather than accepted on the verifier's word; the remaining findings (DemoSeeder watermark trap, `catch (RuntimeException)` catching `QueryException`, `is_breakable` asserted on 2 of 11 catalog tables, no `lang/it/catalog.php`) are latent, unreachable today, and recorded in progress.md as follow-ups.

- [x] 8.1 Content-diff equality: `TranslatableText::sameContent()` + both call sites (D2, D11)
  - `!=` on the i18n-keyed maps recursed into loose VALUE comparison: on PHP 8 two numeric strings compare NUMERICALLY, so `'1e2'` → `'100'` was diffed as "unchanged". In `UpdateProductVariantEnrichment` that swallowed the write, the audit row AND `EnrichmentDataUpdated` — inverting the Requirement's own "an update that actually changes the stored value SHALL record the event". In `UpdateProductMasterIdentity` it versioned + re-armed review while never writing the story.
  - New `TranslatableText::sameContent(?self, ?self)`: order-insensitive over locales (ksort), STRICT over texts (`===`), absence ≡ empty map. The equality lives on the value object, not duplicated in two Actions.
  - Tests: unit — the `==` trap asserted true, `sameContent` asserted false, over 4 numeric-string pairs; key-order, dropped-locale, null/`of([])` cases. Feature — enrichment `'1e2'`→`'100'` records event + audit + write (RED on the old code, verified by reverting); identity story-only numeric change writes the column.
  - Typecheck passes; tests pass

- [x] 8.2 Enforce the D5 verb-collision discipline in `CatalogContentEdit::maintain()` (D5)
  - The discipline was prose only; a `maintain()` call under `rejected`/`identity_updated`/`submitted`/`resubmitted` would make an observational row the freshest review-freshness-relevant action and clear a pending rejection — the S1 hole, re-opened by the mechanism built to close it, with a green suite.
  - `ApprovalGovernance::REVIEW_FRESHNESS_VERBS` promoted `private` → `public` so the rule and its derivation read ONE list; `assertVerbIsNotReviewGoverned()` throws `LogicException` before the transaction (a call-site bug, not a domain rejection). `edit()` exempt by construction.
  - Tests: all four governed verbs rejected with closure un-run and zero audit/event/version writes; both shipped maintenance verbs admitted.
  - Typecheck passes; tests pass

- [x] 8.3 Residual-claim sweep, second pass — the two hits task 7.1 missed
  - `ProductMasterResource::producerLabel()` and `::producerOptions()` still read "a producer is projected only once Parties emits ProducerActivated/Retired — design D3". False since 5.1 (`ProducerCreated` → `registered`). progress.md had both on 7.1's list.
  - Docblock-only; the create-form option list is already correctly unfiltered (creatable ≠ activatable).

- [x] 8.4 `design.md` build-time deviations note (not a rewrite — the decision prose is history)
  - D8 names `lifecycleAction()`; `contentEditAction()` shipped, and every content-edit rejection validates rather than notifies (spec permits either; the split is by action shape).
  - D5 is now a guard, not a comment (8.2).
  - Migration Plan asserts `DemoSeeder` "creates producers through the real actions" — it does not (`Producer::create`), which is what hides the hand-seeded-watermark trap.
