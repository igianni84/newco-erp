---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 23:44 (ralph — `parties-producer-lifecycle` task 5.2 GREEN; CHANGE COMPLETE).** Shipped the final task: `tests/Feature/Modules/Parties/SupplyLifecycleChainTest.php` (new, 5 cases / 67 assertions) — the full-chain integration proof driving the whole supply side through its real Actions + the cross-engine PG17 close. **No production code touched** (all Actions/Events shipped in 2.x–4.x); pure integration test. All 10 tasks now `- [x]`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 475/475 SQLite** (+5 vs 470), **phpstan max 0**, **pint --test clean**, `openspec validate parties-producer-lifecycle --strict` ok. **PG17 verified**: Parties feature+unit + `ModuleBoundariesTest` + `ModulePersistenceConventionsTest` = **119/119 on `postgres:17`** (`:55432`, 512M pest runner). No migration, no composer/migration drift (`git diff main -- composer.json composer.lock database/migrations/` empty), arch tests unamended.

## Active Change & Next Task
- **ACTIVE: `parties-producer-lifecycle`** (APPROVED, branch `ralph/parties-producer-lifecycle`). **10 of 10 done — COMPLETE.** Emitted `<promise>CHANGE_COMPLETE</promise>`.
- **NEXT = human review → merge → semantic-verify → `openspec archive parties-producer-lifecycle --yes`** (GUIDE §2.7; do NOT self-archive/merge). After archive, the truth spec `openspec/specs/party-registry` absorbs the four ADDED requirements + the MODIFIED Birth-States narrowing.
- **Unblocked downstream:** `catalog-lifecycle-approval` (Module 0 consumes the now-emitted `ProducerActivated`/`ProducerRetired` to gate Product Master activation, AC-K-XM-2). The **demand-side** slice (Customer/Account/Profile FSMs, Originating-Club lock, Hero capacity, segment view) is the natural follow-on — mirror this change's shape.

## Blockers & Decisions Needed
- **None.** Scope guard held end-to-end (supply-side only; demand-side proven inert by reflection). Two deferred seams remain documented: KYC-on-activation → `parties-compliance` (DEC-071); all-members-gone-on-close → demand-side.

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin; misleading `NoTestCaseObjectOnCallStack` trace). PHPStan/Pint fine at default mem.
- **PG17 gate** (DB tasks): `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; bounded `docker exec pg pg_isready -q` loop with in-container `docker exec pg sleep 1` (NO host sleep — blocked); run with the `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 …` env prefix; `docker rm -f pg`. Traps in `knowledge/testing/rules.md`.
- **Full-chain integration-proof + reflection scope-guard** (5.2, now in progress.md Codebase Patterns): helper runs every leg via `app(Action)->handle()` (never factories); assert events BY NAME / payloads BY KEY; pin the distinct name-set with `pluck('name')->unique()->values()->all()`+`toEqualCanonicalizing`. **PHPStan-max test traps:** `glob(...) ?: []` (glob is `list<string>|false`); never `class_exists('<hardcoded absent FQCN>')` (flagged `impossibleType`) — reflect the dir listing + `not->toContain`. Demand-side negatives use EXACT names, never `like '%Activated%'`.
- log.md via `memlog.sh`; hot.md ≤550 words; APPROVED = human-only; never `git push`.
