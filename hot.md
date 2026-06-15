---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 23:30 (ralph — `parties-producer-lifecycle` task 5.1 GREEN, committing on `ralph/parties-producer-lifecycle`).** Shipped the **docs-only** task: extended `CONTEXT.md` (glossary of record, NOT protected) with the supply-side lifecycle vocabulary + a new published-contract subsection — six edits, +30/-5, **no code/test/PG17**. Payload keys pulled verbatim from the real `Events/*.php`; spec anchors grep-verified in the PRD.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 470/470 SQLite** (unchanged — docs add no tests), **phpstan max 0**, **pint --test clean**, `openspec validate parties-producer-lifecycle --strict` ok. No migration, no composer drift, no arch-test amendment (`git diff main -- composer.json composer.lock app/* database/migrations/*` shows only already-committed 2.x–4.x code). PG17 not run (docs-only, correct).

## Active Change & Next Task
- **ACTIVE: `parties-producer-lifecycle`** (APPROVED, branch `ralph/parties-producer-lifecycle`). **9 of 10 done.** All six transition Actions + seven events + docs shipped; only the integration test remains.
- **Next = 5.2 `Full supply-side lifecycle chain + cross-engine close`** (LAST TASK — DB-touching → **PG17 GATE REQUIRED**). One feature test `tests/Feature/Modules/Parties/SupplyLifecycleChainTest.php` driving the whole supply-side: create (via `Create*`) → `ActivateProducer` → create+`ActivateProducerAgreement`, then a renewal that supersedes it → `SunsetClub`+`CloseClub` a Club → `RetireProducer` (cascades sunset on remaining `active` Clubs). Assert: `domain_events` holds exactly the **seven** supply-side names with right counts; the two derived chains carry the right `causation_id`/`correlation_id`; the spine **creation** chain still emits no lifecycle event (`SpineCreationChainTest` unamended+green); **demand-side inert** — `OriginatingClubLocked`/`CustomerActivated`/`ProfileActivated` count 0; reflect the `Actions/` namespace and assert NO Customer/Profile transition class + no `originating_club_id` setter. Then run the **entire** Parties suite + the two arch tests on PG17; record it in `progress.md`. On green → re-verify EVERY task's acceptance, full quality loop + `openspec validate --strict`, then emit `<promise>CHANGE_COMPLETE</promise>` (do NOT archive/merge — human does that).

## Blockers & Decisions Needed
- **None.** Scope guard holds (supply-side only). `ProducerActivated`+`ProducerRetired` (shipped) unblock `catalog-lifecycle-approval`.

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin; misleading `NoTestCaseObjectOnCallStack` trace). PHPStan/Pint fine at default mem.
- **PG17 gate** (DB tasks; 5.2 needs it): `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; bounded `docker exec pg pg_isready` wait, in-container `sleep` (NO host sleep — blocked); `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest tests/…/Parties`; `docker rm -f pg`. Traps in `knowledge/testing/rules.md`.
- **Transition Action template** (LIVE ×6, all FSMs complete): `DB::transaction`→`lockForUpdate`→guard throws `Illegal{Entity}Transition::cannotX($from)`→`update(['status'=>New])` (NEVER bump `version`)→`record()` AFTER update (payload=post-transition). Threading params (`?int $causationId,?string $correlationId`) ONLY on cascade-TARGET `SunsetClub`; standalone+SOURCES = `handle(int $id): Model`.
- **`causation_id` = self-FK to `domain_events.id`** → thread a REAL prior id. NULL-safe scope branch (`whereNull` vs `where('club_id',$v)`) — the live PG trap. **`CONTEXT.md` now carries both the supply-side terms + the seven-event contract table** (mirror it for the demand-side change). log.md via `memlog.sh`; hot.md ≤550 words; APPROVED = human-only.
