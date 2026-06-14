---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-14
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-14 (ralph) — `catalog-product-spine` task 2.1 DONE (Format).** First DB-touching spine slice: `catalog_formats` migration (id, name, size_label, volume_ml, `lifecycle_state` + driver-guarded PG CHECK, `version`, `timestampsTz`) + `Format` model + `FormatFactory` + `FormatCreated` event (const NAME/ENTITY_TYPE + static PII-free `payload()`) + `CreateFormat` action (one `DB::transaction`: insert `draft` + record `FormatCreated` via `DomainEventRecorder`, actor from `ActorContext`). Establishes the `Events/`-class + `Create*`-action + transactional-recorder template that 2.2–4.2 repeat (full template in progress.md Codebase Patterns). **2 of 11 tasks done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/catalog-product-spine`: suite **262/262** (930 assertions) on SQLite · phpstan **0 @ max** · pint clean · `openspec validate catalog-product-spine --strict` valid · `git diff main -- composer.{json,lock}` empty.
- **PG17 cross-engine VERIFIED this task: 262/262 on `postgres:17`** (driver guard printed `pgsql` — real PG, not a SQLite fallback). PG17 run stays mandatory for every remaining DB task (2.2–5.3).

## Active Change & Next Task
- **Active change = `catalog-product-spine`** (implementing). 11 tasks, dependency-ordered. 1.1 ✓, 2.1 ✓.
- **NEXT TASK = 2.2 Case Configuration** — migration `catalog_case_configurations` (id, name, `units_per_case`, `packaging_type`, `lifecycle_state`, `version`, `timestampsTz`) **with NO breakability column** + `CaseConfiguration` model + factory + `CaseConfigurationCreated` event + `CreateCaseConfiguration` action. Follow the spine template (progress.md Codebase Patterns) verbatim. Test asserts creation + event AND `Schema::hasColumn('catalog_case_configurations','breakable'|'breakability')===false` (the §7-stays-downstream guard, AC-0-BR-RefData-2). **Verify on PG17 before done.**
- Then 3.1–3.3 Master/Variant/Reference → 4.1–4.2 SKUs → 5.1–5.3 guard/docs/integration.

## Implementation landmines (design D1–D9 + ADR + progress.md Codebase Patterns — read before every task)
- **Spine template established (2.1)** — migration+model+factory+event+action shape is in progress.md Codebase Patterns; reuse verbatim. **Off-convention factory needs a typed `newFactory(): XFactory`** (not just `protected static $factory`) or Larastan infers `mixed` on `X::factory()->create()`.
- **DomainEventRecorder::record(name, module, actorRole, actorId, entityType, entityId, payload, …)** MUST run inside an open `DB::transaction` (the action's own tx satisfies it even under RefreshDatabase). `Module::Catalog->value==='catalog'`. PII-free payloads (ids only); actor from `ActorContext` (System default).
- **5 SQLite↔PG traps** (`knowledge/testing/rules.md`): driver-guard enum CHECK (mirror `domain_events`); assert json/TranslatableText BY KEY (never byte-compare jsonb); `->sole()` for the event row (non-null + asserts exactly-one); named test doubles only. PG17 run mandatory per DB task.
- **producer_id** (lands at 3.1 Master) = plain `unsignedBigInteger`, NO FK / relation / Parties import (arch test stays green unamended). **Composite is producer-agnostic** (D9 — no single-producer check). **Scope guard:** entities born `draft`, only `*Created` events — NO `*Activated`/`*Retired`, no transition path.

## Blockers & Decisions Needed
- None for this slice (crosses NO open gate). Next slice `catalog-lifecycle-approval` (FSM+approval) will need the Identity/auth ADR — not this one.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **Spine template + house enum style + enum-test convention** all in the change `progress.md` Codebase Patterns — read it before 2.2.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB); hot.md ≤550 words. Closing ritual: `openspec list` + unchecked-task count are truth, not the ralph.sh footer.
