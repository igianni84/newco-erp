---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph §5 of `operator-console-parties-producer` — i18n kit-key completeness landed).** Task 5.1 done: added `ProducerConsoleI18nTest` (38 cases / 77 assn), the console's capability-close i18n guard. It enumerates the 18 **kit-contract** keys the kit resolves by string concatenation off `i18nKey()` (invisible to a source scan, so only enumeration catches a drop): `label`/`plural_label` + `columns.{status,kyc_status,version}` + six `actions.*` + seven `notifications.*`, each `Lang::has("operator_console.producer.{$suffix}", 'en', false)`. Five guards: EN baseline; IT-differs (the 16 kit-minus-`label`/`plural_label` keys resolve authored-IT AND ≠ EN); per-key EN fallback for the English-invariant `label`/`plural_label` (CONTEXT.md); IT ⊆ EN over the `producer.*` block; the suite-wide `scanOperatorConsoleHardcodedSinks` reused behind a `function_exists` guard, scoped to `ProducerResource*` → zero hardcoded sinks. No source/lang change — §1–§4 authored the full key list; §5 only asserts it.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Branch `ralph/operator-console-parties-producer` GREEN: full suite 1205/1205 SQLite (6797 assn), phpstan 0, pint clean, `openspec validate --strict` OK, composer diff vs main empty.** (+67 vs the 1138 `main` baseline; +38 this iteration.) PG17 not yet re-run this change — the PG17 full gate is the §6 closing-chain task (6.1).
- **Run-cmd gotchas:** full suite OOMs under bare `php artisan test` (128M) → use `php -d memory_limit=-1 vendor/bin/pest` and `… vendor/bin/phpstan analyse`. **NEW (cost a red):** a test that REUSES a sibling file's top-level helper (e.g. `scanOperatorConsoleHardcodedSinks`) must be verified with `--filter=<TestName>` or the full suite — a bare file path loads only that file, the helper is undeclared, and the `function_exists` guard false-reds. `openspec` on PATH. PG17: docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active: `operator-console-parties-producer`** (APPROVED). 10 of 11 tasks done (§1–§5 complete). **§6 is the FINAL task → after it, reply `<promise>CHANGE_COMPLETE</promise>`.**
- **Next: §6 — task 6.1 (PG17 closing-chain).** Add `ProducerConsoleChainTest.php`: one `it()` driving a Producer through the console **pages** — `Livewire::test(CreateProducer::class)->fillForm([...])->call('create')`, then `Livewire::test(ViewProducer::class, ['record'=>$id])->callAction('requireKyc')`→`callAction('verifyKyc')`→`callAction('activate')`; seed the Producer operating two `active` Clubs (event-free via `Club::factory()`), then `callAction('retire')`. Assert `DomainEvent::pluck('name')->all()` `->toEqualCanonicalizing(['ProducerCreated','ProducerActivated','ProducerRetired','ClubSunset','ClubSunset'])` (KYC steps add NO event); a `foreach` asserting every event `module==='parties'`, `actor_role===ActorRole::NewcoOps`, `actor_id` non-null; a representative `actor_id` == the acting operator (loose `toEqual` — PG numeric string); both `ClubSunset` carry the `ProducerRetired` id as `causation_id`. Green on **SQLite AND PG17** (the §6 PG17 gate). Read `design.md` (D1–D8) + `progress.md` Codebase Patterns each iteration.

## Blockers & Decisions Needed
- None. Reminder: ralph commits locally; **humans push**. No open ADR gate is crossed by this change.

## Open Patterns
- **i18n completeness recipe is now the Parties template (§5).** Club/Agreement/Customer each get one `<Entity>ConsoleI18nTest`: enumerate kit-contract keys via `Lang::has(…,'en',false)`, derive IT-differs as `array_diff(kitKeys,['label','plural_label'])`, reuse (never redeclare) `scanOperatorConsoleHardcodedSinks` behind a `function_exists` guard scoped to `<Entity>Resource*`. Consolidated in the change's progress Codebase Patterns.
- **One View page, two FSMs (§4)** + the lifecycle/KYC test recipes remain the live templates for the rest of Parties.
