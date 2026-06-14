---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-14
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-14 (ralph) — `catalog-product-spine` task 1.1 DONE (enums).** First implementation slice of Module 0 landed: `App\Modules\Catalog\Enums\ProductType` (sole `Wine='wine'`) + `LifecycleState` (`draft/reviewed/active/retired`), pure house-style case-list enums (D2/D3) + `tests/Unit/Modules/Catalog/Enums/EnumsTest.php` (4 tests). No DB this task. The first impl commit also folded the still-uncommitted authoring ADR (`decisions/2026-06-14-catalog-category-neutral-representation.md` + INDEX row) onto the branch. **1 of 11 tasks done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15.0 · Filament 5.6.7 · Pennant 1.23.0 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/catalog-product-spine`: suite **258/258** (910 assertions) on SQLite · phpstan **0 @ max** · pint clean · `openspec validate catalog-product-spine --strict` valid · `git diff main -- composer.{json,lock}` empty.
- No PG17 run this iteration (task 1.1 is enums-only, no DB) — PG17 cross-engine becomes mandatory from task 2.1 on.

## Active Change & Next Task
- **Active change = `catalog-product-spine`** (implementing). 11 tasks, dependency-ordered. 1.1 ✓.
- **NEXT TASK = 2.1 Format** — FIRST DB-touching task: migration `catalog_formats` (bigint id, name, physical-measure cols, `lifecycle_state` string + driver-guarded CHECK + cast default `draft`, audit/version) + `Format` model + `FormatFactory` + `FormatCreated` event (establishes the `Events/` one-class-per-event convention) + `CreateFormat` action (insert + record `FormatCreated` in one `DB::transaction`). Test `tests/Feature/Modules/Catalog/FormatTest.php` with `RefreshDatabase`. **Verify on PG17 before done.**
- Then 2.2 CaseConfiguration → 3.1–3.3 Master/Variant/Reference → 4.1–4.2 SKUs → 5.1–5.3 guard/docs/integration.

## Implementation landmines (design.md D1–D9 + ADR — read design.md before every task)
- **DomainEventRecorder::record(name, module, actorRole, actorId, entityType, entityId, payload, …)** MUST run inside an open `DB::transaction` (throws `NotInTransactionException` otherwise) — that's the atomicity guard. `Module::Catalog->value === 'catalog'`. PII-free payloads (ids only); actor from `ActorContext`/`ActorRole`.
- **5 SQLite↔PG traps** (`knowledge/testing/rules.md`): driver-guard enum CHECK (`DB::getDriverName()==='pgsql'`, mirror the `domain_events` migration); assert json/TranslatableText by key (never byte-compare jsonb); named test doubles only. PG17 run mandatory per DB task.
- **`producer_id` is a plain `unsignedBigInteger`** — NO DB FK, NO Eloquent relation, NO Parties import (boundary law; arch test must stay green unamended). Per-type attribute tables (`catalog_*_wine_attributes`, 1:1 within-module hasOne OK); `appellation` a real column; dedup at creation (in-tx join, BR-Identity-1).
- **Composite is producer-agnostic** (D9) — do NOT add a single-producer check (Module S's job). **Scope guard:** entities born `draft`, NO FSM/transition/approval, only `*Created` events — NO `*Activated`/`*Retired`.

## Blockers & Decisions Needed
- None for this slice (crosses NO open gate). Next slice `catalog-lifecycle-approval` (FSM+approval) will need the Identity/auth ADR — not this one.
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).

## Open Patterns
- **House enum style + enum test convention** now consolidated in the change `progress.md` Codebase Patterns — read it before 2.x.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB); hot.md ≤550 words. Closing ritual: `openspec list` + unchecked-task count are truth, not the ralph.sh footer.
