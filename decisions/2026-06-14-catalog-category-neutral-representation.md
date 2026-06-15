---
type: decision
status: active
date: 2026-06-14
---

## Decision: Category-neutral PIM core + per-type attribute tables

The Catalog (Module 0) product spine stores **category-neutral identity/structural fields on the core entities** (Product Master, Product Variant, Product Reference) and holds **all type-specific attributes in dedicated per-type attribute tables**, one per (entity, Product Type) that has them. At launch the only Product Type is `WINE`, so the per-type tables are:

- `catalog_product_master_wine_attributes` — 1:1 with `catalog_product_masters`; columns: `appellation`, `region`, and translatable descriptive prose (winery story) via the `TranslatableTextCast` JSON column.
- `catalog_product_variant_wine_attributes` — 1:1 with `catalog_product_variants`; columns: `vintage_year` (nullable) + `non_vintage` marker, and translatable vintage-level prose (tasting notes, critic-score text) via `TranslatableTextCast`.

The identity-bearing `appellation` is a **real, indexable column** on the per-type table (not a value buried in JSON). The `WINE` uniqueness key (`producer_id + product name + appellation`) is enforced by an **in-transaction deduplication check at creation** (BR-Identity-1) — a plain-column join over `catalog_product_masters` ⋈ `catalog_product_master_wine_attributes` for a non-retired collision — which is exactly the "deduplication check" the PRD mandates. Because `appellation` is a real column, that check (and any supporting index on it) is identical on PostgreSQL and SQLite; option (1) below would force a jsonb functional index on PG with a divergent SQLite path. A single **DB-level** unique constraint is not used here because the identity tuple deliberately spans the neutral core (`producer_id`, `name`) and the per-type table (`appellation`); a single-table backstop (e.g. for the future bulk-import path) is left as additive hardening. A future Product Type is added by introducing its own `*_<type>_attributes` table(s); the neutral core and the cross-module event contract are never reshaped.

## Context: why this came up

`spec/04-decisions/decisions.md` DEC-073 (and the Module 0 PRD §3.9 / §16) explicitly delegate the **physical representation** of the category-neutral model to the dev team, fixing only two constraints: the core entities stay category-neutral, and per-type attribute sets are **additive** (a new type must not reshape the core). The §16 guardrails forbid a dynamic EAV / rules engine and demand "category-readiness, not maximal configurability." The `catalog-product-spine` change must pick a representation before any migration is written, because it shapes every spine table.

The binding wrinkle: for `WINE`, the identity-uniqueness key includes `appellation` (PRD §13.1 BR-Identity-1). Whatever holds `appellation` must support a unique index that is enforceable on **both** CI engines (SQLite dev/test, PostgreSQL 17 production — ADR `2026-06-12-production-db-engine`).

## Alternatives considered

1. **JSON attribute column on the core** — a single `wine_attributes` (jsonb) column per core entity. Simplest and trivially additive, but `appellation` then lives inside JSON, so the identity uniqueness index needs a PostgreSQL functional/expression index over jsonb **plus** a separate SQLite-compatible path (SQLite cannot index a jsonb-extracted value the same way) — exactly the SQLite-vs-PG asymmetry `knowledge/testing/rules.md` warns about, on the most identity-critical constraint in the module.
2. **Per-type attribute tables (chosen)** — wine attributes in dedicated 1:1 tables; `appellation` a real column; prose via the existing `TranslatableTextCast`. Best-modelled per the §3.9 intent guard; additive (new type → new table); the unique index is over plain columns and portable. Heavier (an extra table + join per typed entity).
3. **Single shared EAV table** — `(entity_id, attribute_key, value)` rows. Maximally flexible but **explicitly forbidden** by §16 guardrail 2 (no dynamic EAV / rules engine). Rejected outright.
4. **Promote wine attributes onto the core** — put `appellation`/`vintage` directly on the core entities. Simplest indexing, but **violates** the category-neutral-core constraint (§16 guardrail) — the core would carry wine-only columns. Rejected.

## Reasoning: why this option won

Per-type tables give `appellation` a real column, so the launch-critical identity/dedup check (BR-Identity-1) runs as a plain-column join query — **portable across SQLite and PostgreSQL** with no jsonb functional-index asymmetry — removing the single riskiest portability trap from the most important constraint in the module. It is the most faithful reading of the §3.9 intent guard ("keep wine concrete and well-modelled; a neutral core + additive per-type attribute sets is sufficient"), and it is genuinely additive: a future Product Type contributes its own attribute table and the neutral core + event contract are untouched. The extra join cost is negligible at PIM scale and read-side only.

## Trade-offs accepted

- An extra table and a 1:1 join per typed entity (Master, Variant) versus a single JSON column — accepted for the indexability and modelling clarity.
- Adding a future Product Type is a schema migration (its attribute table), not a config change — accepted and intended: per §16, category expansion is a deliberate, reviewed workstream, not runtime configurability.
- The per-type-table convention must be documented so future types follow it consistently (recorded here + in the `catalog-product-spine` design.md).

## References

- spec/02-prd/Module_0_PRD_v0.3-MVP.md § 3.1, § 3.2, § 3.3, § 3.9, § 13.1 (BR-Identity-1), § 16 (generalisation + guardrails)
- spec/04-decisions/decisions.md DEC-073 (representation delegated to the dev team) · spec/04-decisions/MVP_Decisions_Register_v0.1.md MVP-DEC-004 / DEC-065 (generalisation folded into v0.3-MVP)
- decisions/2026-06-12-production-db-engine.md (Postgres-truthful, SQLite-compatible migrations; pgsql CI lane) · knowledge/testing/rules.md (the five SQLite-vs-PG portability traps)
- openspec/specs/i18n/spec.md (Translatable text — the JSON cast reused for descriptive prose)
- openspec/changes/catalog-product-spine/ (the change this decision shapes)
