---
type: decision
status: active
date: 2026-06-12
---

## Decision: Production database engine is PostgreSQL (managed, EU)

Production runs **PostgreSQL, floor version 17** (provision the latest major the chosen managed provider offers at provisioning time). Dev/test stays **SQLite** (`:memory:` in tests) per the stack ADR — this decision covers production only. Baseline fixed now because this ADR's gate (first Module 0 migration, F1 foundations) arrives *before* the hosting gate (F7): domain migrations are written before any production database exists.

**Baseline:**
- **Encoding/collation:** UTF-8; database default collation `C.UTF-8` (bytewise, stable, fast). Locale-aware ordering is opt-in via explicit ICU collations per column/query, only on user-facing surfaces that need it. The spec demands full Unicode incl. JP/ZH but no per-locale collation and no full-text search (grep-verified absence).
- **Extensions: none required at launch.** Any future extension is an explicit decision — each one narrows provider portability and breaks SQLite parity.
- **Migration policy — "Postgres-truthful, SQLite-compatible":** schema is written for production truth (CHECK constraints, partial indexes, expression indexes — all expressible in SQLite too); if a PG feature ever lacks an SQLite equivalent, the fallback is documented in the migration and PG remains the truth.
- **Parity guardrail:** a `pgsql` CI lane (PostgreSQL service container, matrix next to SQLite, every push) lands in the same F1 change that creates the first domain migration.

**What this ADR does NOT decide** (open gates untouched): hosting provider (F7 — founder's probable direction recorded as non-binding input: hyperscaler EU region, e.g. RDS/Aurora/Cloud SQL); audit/financial-event store mechanism (own ADR); queue driver; domain-event substrate; search/FTS (if ever needed: Laravel Scout + external engine, DB-independent).

## Context

The CLAUDE.md "Open stack decisions" table gates the first Module 0 migration (and all F1 foundations) on this ADR. The spec is deliberately engine-agnostic: zero PostgreSQL/MySQL mentions in all of `spec/` (grep-verified); `Architecture_v0.3-MVP.md` §0.5/§7.1 place "database engine" in dev-team scope (DEC-073), and `Build_Workplan_v0.3-MVP.md` Phase 1 prescribes exactly this signed-off ADR ("persistence: engine, migration discipline, transaction patterns").

The binding constraints are indirect:
- **Contention, not throughput.** Hold-placement ≤200ms p99, ATP push sub-1s, storefront staleness ≤5s (Module B §22.1 — the spec's only numeric NFRs); strongly-consistent multi-row counters at the transactional boundary (L1 `qty − issued ≥ 0` per sub-pool, Module A §7.1; L2 four-subtrahend formula + NS batch counters with explicit "no inconsistent interleaving" atomicity, Module B §5.3/§10.4–10.5). Launch volumes explicitly low (Module E §7.1: "Xero rate limits are not a constraint at launch volumes"); fine-wine drops mean many buyers racing the last bottle.
- **Structural JSON prescribed by the spec:** "i18n-keyed JSON per attribute" (Architecture §1.2/§5.1; Module 0 §8 — adding a locale is configuration, not migration) and financial-event payloads queried later by settlement aggregation (Module E §4.7: "the engine reads the same recorded events").
- **Append-only 10-year retention** for financial events and provenance, mechanism explicitly open (Module E §7.6; Module B BR-B-Provenance-1) — the engine must not foreclose the audit-store ADR.
- **"Schema-migration safety documented"** + tested backup/restore/DR drill (Build_Workplan Phase 7); GDPR erasure is PII-overwrite-in-place on surviving rows, never DELETE (Module K §8.2/§12); EU data residency (Architecture §5.4).

Grill outcome (founder, 2026-06-12): no operational-experience bias toward either engine (Q1); hosting direction probably hyperscaler EU region → the engines tie on that axis and it was dropped from the decisive arguments (Q2); baseline confirmed (Q3); parity policy + CI lane confirmed (Q4).

## Alternatives considered

- **MySQL 8.4 LTS (managed, EU).** Its honest case: native CJK full-text (ngram/MeCab parsers) — the one axis it wins; ubiquitous managed offers; CHECK enforced since 8.0.16, functional indexes since 8.0.13. Rejected because the axis it wins is one the spec never asks for (no FTS requirement; browsing is structured eligibility filters, Module S §4; fine-wine catalog scale is thousands of SKUs), while it loses axes this codebase lives on daily: **no partial indexes** (permanent divergence from both SQLite-in-tests and the invariant-enforcement vocabulary), JSON indexable only via generated columns (each newly-indexed JSON path is a migration — in tension with "new locale = configuration"), **non-transactional DDL** (a failed multi-statement migration leaves partial state to clean up by hand), **no foreign keys on partitioned tables** (narrows the 10-year event-store design space), InnoDB gap/next-key locks adding deadlock surface on contended ranges.
- **PostgreSQL in dev too (Docker), dropping SQLite.** Out of scope by explicit founder declaration (dev/test stays SQLite — fast, zero-setup, `:memory:`). Its substance survives as the parity guardrail: the `pgsql` CI lane tests against real PostgreSQL on every push once domain migrations exist.

## Reasoning

1. **SQLite parity of the enforcement vocabulary** — the load-bearing argument given dev/test stays SQLite. CHECK constraints, partial indexes, expression indexes, generated columns, `RETURNING`: SQLite and PostgreSQL share all of it, so schema-level invariant guards (no-oversell counter CHECKs, partial uniques like "one ACTIVE allocation per key") are written once and behave the same in tests and production. MySQL cannot express partial indexes at all.
2. **JSONB + GIN/expression indexes** natively serve the spec's two structural JSON uses: per-attribute i18n documents and queryable financial-event payloads.
3. **Transactional DDL:** a failed migration rolls back clean — directly serving Phase 7 "schema-migration safety" for a solo founder with no DBA.
4. **FKs on partitioned tables** keep time-partitioning of the 10-year append-only store possible without dropping referential integrity — maximum room for the audit-store ADR.
5. **Fewer contention traps:** plain row locks (no gap-lock surface) under the well-trodden Laravel `lockForUpdate()` pattern; advisory locks available if per-allocation serialization is ever needed.
6. **EU managed availability is first-class on both plausible hosting paths** (hyperscaler EU region now; EU-sovereign providers — Aiven, Scaleway, OVH, Clever Cloud — remain open and are notably richer on the Postgres side).

## Trade-offs accepted

- **Weak native CJK full-text** vs MySQL's ngram/MeCab: accepted — the spec requires no FTS; if search materializes, Scout + external engine is the idiomatic, engine-independent answer.
- **Dev/prod dialect divergence remains** (SQLite vs PostgreSQL): managed via the Postgres-truthful policy + `pgsql` CI lane rather than eliminated; cost is one extra CI job and occasional dialect-conditional migration code.
- **Bytewise default ordering** (`C.UTF-8`): linguistically correct ordering is explicit per-surface opt-in work; gained in exchange: index stability across provider/ICU/glibc upgrades and faster comparisons.
- **PG 17 floor** excludes any provider lagging behind 17 — acceptable in 2026 (all candidate providers offer it).

## References

`spec/02-prd/Architecture_v0.3-MVP.md` §0.5, §1.2, §5.1, §5.4, §7.1 · `spec/05-release/Build_Workplan_v0.3-MVP.md` Phase 1, Phase 7 · `spec/02-prd/Module_B_PRD_v0.3-MVP.md` §5.3, §10.4–10.5, §22.1, §18 (BR-B-Provenance-1) · `spec/02-prd/Module_E_PRD_v0.3-MVP.md` §4.7, §7.1, §7.2, §7.6 · `spec/02-prd/Module_0_PRD_v0.3-MVP.md` §8 · `spec/02-prd/Module_A_PRD_v0.3-MVP.md` §7.1, §10 · `spec/02-prd/Module_S_PRD_v0.3-MVP.md` §4 · `spec/02-prd/Module_K_PRD_v0.3-MVP.md` §8.2, §12 · `spec/04-decisions/` DEC-073 · [[2026-06-11-stack-versions-and-filament-ai-tooling]] · [[2026-06-11-modular-monolith-architecture]]
