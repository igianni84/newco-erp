---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph loop — `operator-console-catalog-spine` task 5.1 i18n EN+IT + sink scan DONE; 9/10).** The spine's **capability-close i18n guard**, mirroring the predecessor's `ProductMasterConsoleI18nTest` across the six entities. **No lang file changed** — tasks 2.1–4.1 each authored their `operator_console.<entity>.*` block as they shipped, so EN was already complete and `IT ⊆ EN` already held; 5.1 PROVES it (one new test file, `SpineConsoleI18nTest`, 37 tests / 192 assertions). Six concerns: (1) **EN completeness for the 17 kit-resolved keys per entity** — the keys the kit concatenates in the BASE classes (label/plural_label/columns.{lifecycle_state,version}/actions.{5 verbs}/fields.rejection_notes/affordance.second_actor/notifications.{5 success + action_failed}); **this is the only guard that catches a dropped kit key** (invisible to a source scan; a missing key passes every behaviour test because `__()` renders the raw key as an ugly-but-functional label). (2) EN completeness for the per-entity LITERAL keys (regex scan of `Resources/Catalog` minus ProductMaster). (3) Italian rendering — 22 representative keys, all six (`__===trans(it)` AND `!==trans(en)`; proper-noun labels like `product_reference.columns.variant`="Product Variant" excluded — equal in both locales). (4) per-key EN fallback (`<entity>.label` omitted from `it`). (5) IT⊆EN scoped to the six. (6) sink scan over the six reusing the predecessor's generically-named `scanOperatorConsoleHardcodedSinks` (+ `function_exists` non-vacuity guard — no redeclaration, no copy; phpstan sees it defined in the predecessor file).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 1137/1137 SQLite (6192 assertions) — +37 vs the 1100 baseline (the new i18n test file).** phpstan 0; pint clean. **No PG17 run for 5.1 (DB-free** — pure locale + static source scan; the task explicitly excludes PG17). composer.json/lock diff vs main empty; no migrations; only ONE new file (`tests/.../SpineConsoleI18nTest.php`), no protected files, no lang/app change.
- **Run-cmd gotcha:** the FULL suite OOMs under bare `php artisan test` (128M). Run `php -d memory_limit=-1 vendor/bin/pest` and `php -d memory_limit=-1 vendor/bin/phpstan analyse`. PG17 (for 5.2): docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **ACTIVE:** `operator-console-catalog-spine` — 9/10 tasks done (`APPROVED` present). All SIX spine consoles shipped + i18n-guarded.
- **Next: task 5.2 (LAST)** — **full-spine demo-path chain test + cross-engine PG17 close**. One feature test driving `Product Master → Variant → Reference → {Sellable, Composite}` to `active` THROUGH the consoles (parent-before-child), exercising along the way: cascade gate (child activate blocked under non-active parent), the two create form-errors (PR duplicate, Composite `<2`), the reference-integrity blocks (retire a PR / Case Configuration under an active SKU), reopen. Assert every write carries `actor_role: newco_ops`; the `*Created`/`*Activated`/`*Retired` set is exactly the entities touched with NO event for submit/reject/reopen (`where('name','like','%Reviewed%'|'%Reopened%')->count()===0`); seed producers **event-free** (`ProducerState::create([... Active ...])`) so `domain_events` holds only operator-driven catalog writes; snapshot `DomainEvent` ids before each multi-event step + `toEqualCanonicalizing`. Confirm/add `CONTEXT.md` **Operator console** term. Run the **entire** OperatorPanel + Catalog + arch/boundary suites on **PG17** (cross-engine close — a PG17 task), record it, `openspec validate --strict` → `<promise>CHANGE_COMPLETE</promise>`.

## Blockers & Decisions Needed
- None. **`main` is LOCAL-ONLY — not pushed.** Humans push; the loop only commits locally.

## Open Patterns
- **READ `progress.md` `## Codebase Patterns` before 5.2** — kit pieces + standalone/hierarchical/leaf/N-constituent recipes + the **kit-key i18n-completeness** pattern (5.1, top) + the full-chain test idioms (the 5.2 hint in the 5.1 progress entry: event-free producer seeding, `toEqualCanonicalizing`, the `%Reviewed%/%Reopened%` zero-count assertion, the `actor_role` foreach).
- 5.2 reuses the predecessor's `ProductMasterConsoleChainTest` shape (uniquely-prefixed self-contained helpers) generalised across the spine; it IS a PG17 task (the cross-engine close).
