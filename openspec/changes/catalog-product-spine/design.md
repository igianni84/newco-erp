## Context

First real-module slice of Phase 2. The F1 platform substrate is complete and green on both CI engines: `App\Platform\Events\DomainEventRecorder::record(name, module, actorRole, actorId, entityType, entityId, payload, correlationId?, causationId?)` (must run inside an open `DB::transaction`), the `App\Modules\Module` enum (`Module::Catalog->value === 'catalog'`), `App\Platform\Events\{ActorRole, ActorContext}`, and `App\Platform\I18n\{TranslatableText, TranslatableTextCast, SupportedLocale}`. The Catalog module is a bare skeleton (`App\Modules\Catalog\Providers\CatalogServiceProvider`). The boundary law is enforced by `tests/Architecture/ModuleBoundariesTest.php`: code under `App\Modules\Catalog` may not import another module's namespace except its `Contracts\*`/`Events\*` surface — so a producer reference is a plain id column, never an Eloquent relation.

This change builds the structural product spine only; the representation choice it turns on is recorded in `decisions/2026-06-14-catalog-category-neutral-representation.md`.

## Goals / Non-Goals

**Goals:**
- The seven spine entities as `catalog_*` tables + Eloquent models, with their identity and relationship invariants enforced.
- The §16 category-neutral split (neutral core + per-type WINE attribute tables), `Product Type` = `WINE` only.
- The §18 naming cascade as canonical code naming; wine-display aliases documented.
- `lifecycle_state` stored (born `draft`); each entity records its `*Created` event through the substrate, transactionally, PII-free.
- Green on SQLite **and** verified on local PostgreSQL 17 before close.

**Non-Goals (deferred — see proposal slice-boundary table):**
- Any lifecycle **transition**, the Creator→Reviewer→Approver approval, rejection handling, activation/retirement cascades, the Producer-activation gate, and `*Activated`/`*Retired` events → `catalog-lifecycle-approval`.
- PR immutability-once-referenced enforcement; Composite atomicity-at-sale / post-Offer immutability (no referencers exist yet).
- The Layer-1 breakability whitelist → `catalog-breakability-whitelist`.
- The LWIN/Liv-ex enrichment adapter (manual baseline only here) → `catalog-enrichment-lwin-adapter`.
- Read API, Filament operator UX, bulk import, Bottle-Page.

## Decisions

**D1 — Category-neutral core + per-type attribute tables** (ADR `2026-06-14-catalog-category-neutral-representation`). Tables: `catalog_product_masters` (neutral) + `catalog_product_master_wine_attributes` (1:1; `appellation`, `region`, translatable `winery_story`); `catalog_product_variants` (neutral) + `catalog_product_variant_wine_attributes` (1:1; `vintage_year` nullable, `non_vintage` bool, translatable `tasting_notes`). Within-module 1:1 — an Eloquent `hasOne`/`belongsTo` between a core entity and its own per-type table is allowed (same module). Descriptive prose uses `TranslatableTextCast` (one `json` column per attribute).

**D2 — `Product Type` is a backed enum, not a table.** `App\Modules\Catalog\Enums\ProductType: string { case Wine = 'wine'; }` — mirrors the house enum style (`Module`, `Currency`, `SupportedLocale`); §16 forbids EAV/dynamic configurability, so a single-row reference table would be over-modelling. A future type is a new enum case + its attribute table(s). Stored as a string column on the Master with a Postgres `CHECK` (driver-guarded) + the enum cast (same pattern as `domain_events.actor_role`).

**D3 — `lifecycle_state` is a backed enum, transitions deferred.** `App\Modules\Catalog\Enums\LifecycleState: string { Draft='draft'; Reviewed='reviewed'; Active='active'; Retired='retired'; }`. Every spine table carries a `lifecycle_state` column (string + driver-guarded `CHECK` + enum cast), defaulted/created `draft`. This change writes **no** transition — there is no review/approve/activate/retire method and no `*Activated`/`*Retired` emission. The enum's full domain exists so `catalog-lifecycle-approval` can drive it without a migration.

**D4 — Keys & cross-module references.** Primary keys are bigint `$table->id()` (consistent with the substrate tables; PIM ids are not customer-facing). Within-module FKs use `->constrained('catalog_*')`. The **producer reference is a plain `unsignedBigInteger('producer_id')` with NO database foreign key and NO Eloquent relation** — Module K's tables do not exist yet and a cross-module FK/relation violates the boundary law (invariant 10). Producer validity (must be an active, KYC-verified producer) is a *lifecycle-gate* concern handled later by consuming `ProducerActivated`; this change stores the id only.

**D5 — Entity / table map.**
- `catalog_formats` (reference; no parent), `catalog_case_configurations` (reference; **no breakability column**).
- `catalog_product_masters` → `catalog_product_variants` (FK `product_master_id`) → `catalog_product_references` (FKs `product_variant_id`, `format_id`; **unique `(product_variant_id, format_id)`**; **no `case_configuration_id`**).
- `catalog_sellable_skus` (intrinsic; FKs `product_reference_id`, `case_configuration_id`, + commercial attrs).
- `catalog_composite_skus` + `catalog_composite_sku_constituents` (join: `composite_sku_id`, `product_reference_id`, `position`; unique `(composite_sku_id, product_reference_id)`). M:N; a PR may recur across composites.

**D6 — Identity dedup at creation.** For `WINE`, creation runs an in-transaction join check (`catalog_product_masters` ⋈ `catalog_product_master_wine_attributes`) for a non-retired row matching `producer_id + name + appellation`; a collision rejects with a clear, localized reason (BR-Identity-1 / AC-0-J-3). `appellation` is a real column so this is a plain portable query (the reason per-type tables were chosen). No cross-table DB unique constraint (the tuple spans two tables); a single-table backstop is deferred to bulk-import.

**D7 — Naming cascade realised (§18).** Model classes: `ProductMaster`, `ProductVariant`, `ProductReference`, `Format`, `CaseConfiguration`, `SellableSku`, `CompositeSku`. No `Wine*`/`BottleReference*` structural identifier anywhere; "Wine Master / Wine Variant / Bottle Reference (BR)" appear only as wine-display aliases in class docblocks and `CONTEXT.md`. Event classes live under `App\Modules\Catalog\Events\` (the module's public surface), one per `*Created` event, named verbatim per §14.1.

**D8 — Creation events via explicit Actions.** Each entity gets a small creation Action (`App\Modules\Catalog\Actions\Create*`) that, inside one `DB::transaction`, inserts the row(s) (core + per-type attrs) and calls `DomainEventRecorder::record(...)` with the verbatim event name, `Module::Catalog->value`, the `ActorContext`-resolved role/id, `entityType` (e.g. `'ProductMaster'`), the new id (stringified), and a **PII-free payload referencing the producer only by id**. The recorder throwing `NotInTransactionException` outside a transaction is the guard that keeps emission atomic with the write. Models stay persistence-only; the Action is the seam the lifecycle change extends.

**D9 — Composite is producer-agnostic.** `CreateCompositeSku` validates **N ≥ 2** constituents and that each constituent PR exists; it MUST NOT validate producer composition (single-producer admissibility is Module S's Offer-publication rule — BR-SKU-5). This is a deliberate non-check; a "helpful" producer-uniformity guard here would be a boundary violation.

## Risks / Trade-offs

- **SQLite-green is necessary, never sufficient** → every DB-touching task must be verified on a real PostgreSQL 17 before close (`knowledge/testing/rules.md`). The traps live in: `jsonb` key-reordering on the `TranslatableText` columns (assert by key / through the cast, never byte-compare); `timestamptz` `+00` suffix if any raw timestamp is asserted; enum `CHECK` constraints (driver-guard the `ALTER TABLE … CHECK` with `DB::getDriverName()==='pgsql'`, mirror `domain_events`); named test doubles only for anything persisted (no anonymous-class NUL-byte collisions). The `domain_events` write path (uuid `event_id`) is the recorder's concern, already portable.
- **Cross-module FK temptation** → `producer_id` with a DB foreign key or a `belongsTo(Producer)` would pass nothing (Parties has no table) and break the boundary test. Mitigation: plain column, documented in D4; the arch test catches the relation, not the FK, so reviewers must watch the migration too.
- **Scope creep into lifecycle** → the entities *want* an `activate()` the moment they exist. Mitigation: D3 states the non-goal as a spec requirement ("no transition path exists in this change"); a test asserts created entities stay `draft` and that no `*Activated`/`*Retired` event is recordable.
- **Dedup race** → application-layer dedup has a theoretical race under concurrent creation; at operator-scale manual PIM entry this is negligible, and bulk-import (deferred) is where a DB backstop is added. Accepted.
- **i18n discipline** → rejection reasons and any operator-facing copy go through Laravel localization (no hardcoded strings, invariant 12); descriptive prose uses `TranslatableText`.

## Migration Plan

Additive only — new `catalog_*` tables, no data, no change to existing schema. Build in dependency order: enums → reference entities (Format, Case Configuration) → Master (+ wine attrs) → Variant (+ wine attrs) → Product Reference → Sellable SKU (Intrinsic) → Composite SKU (+ constituents) → creation Actions + `*Created` events → docs (`CONTEXT.md`, contract note). Rollback is dropping the `catalog_*` tables (and the ADR/ docs revert with the branch). No production data exists.

## Open Questions

- **Creation-logic home** — explicit Actions (D8, recommended: testable, the lifecycle seam) vs. an Eloquent `created` observer. Picked Actions; revisit only if the lifecycle change finds the observer cleaner.
- **DB backstop unique index** for the identity key — deferred to the bulk-import change (D6). Flag if a concurrency need surfaces earlier.
