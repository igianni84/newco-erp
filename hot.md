---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 22:34 (ralph — `parties-producer-lifecycle` task 2.2 GREEN, committing on `ralph/parties-producer-lifecycle`).** Shipped `CloseClub` + `ClubClosed` (`sunset → closed`), COMPLETING the Club FSM `active → sunset → closed`. Near-twin of 2.1 with the guard flipped to `=== Sunset` and a simpler signature (closure is never a cascade target). DB-touching → PG17 verified.

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **Full suite 451/451 SQLite** (+3 vs 448), **phpstan max 0**, **pint clean**, `openspec validate parties-producer-lifecycle --strict` ok. **PG17 verified: 92/92** Parties (Feature+Unit) on `postgres:17` `:55432`. No migration, no composer drift, no arch-test amendment (verified `git diff main`).

## Active Change & Next Task
- **ACTIVE: `parties-producer-lifecycle`** (APPROVED, branch `ralph/parties-producer-lifecycle`). **4 of 10 tasks done.**
- **Next = 3.1 `ActivateProducer` + `ProducerActivated`** — `Events/ProducerActivated` (`NAME='ProducerActivated'`, `ENTITY_TYPE='Producer'`, `payload(Producer)` → `{producer_id, status}`) + `Actions/ActivateProducer::handle(int $producerId): Producer` (SIMPLE signature — standalone, non-cascade-target) following the **transition Action template**: `DB::transaction` → `lockForUpdate` re-read → assert `status === Draft` (else `IllegalProducerTransition::cannotActivate`) → `update(['status'=>Active])` → `record(ProducerActivated)` (root event, no threading args). **NO KYC gate** (deferred seam L8 — document in docblock; activation succeeds with no KYC verdict present). Extend `tests/Feature/Modules/Parties/ProducerLifecycleTest.php` (exists from 1.2). A non-`draft` activation MUST throw + record nothing. DB-touching → **must verify PG17**.
- Then: 3.2 RetireProducer (+`ProducerRetired` + cascade walking `Producer::clubs()`, CALLS `SunsetClub` threading `$retired->id`/`->correlation_id`) → 4.1 ActivateProducerAgreement (NULL-safe supersession) → 4.2 Terminate → 5.1 docs → 5.2 chain+cross-engine close.
- Emits `ProducerActivated`/`ProducerRetired` (3.x) → unblocks `catalog-lifecycle-approval`.

## Blockers & Decisions Needed
- **None.** Two documented seams ship ungated (tightened later): KYC-on-activation → `parties-compliance`; all-members-gone-on-close → demand-side (now LIVE & documented in `CloseClub`). Scope guard holds: supply-side transitions only; no Customer/Account/Profile transition, no `originating_club_id` mutation.

## Open Patterns
- **Full-suite runner = `php -d memory_limit=512M vendor/bin/pest`** — NOT `php artisan test` (128M OOMs in arch plugin; crash masquerades as `NoTestCaseObjectOnCallStack`/`stream_filter_remove` shutdown trace, real cause "memory exhausted" at head). PHPStan + Pint fine at default mem.
- **PG17 gate** (every DB-touching task): `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; wait via bounded `docker exec pg pg_isready` loop, pacing with in-container `sleep` (NO host foreground sleep — it's blocked); `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest tests/…/Parties`; `docker rm -f pg`. Five SQLite↔PG traps in `knowledge/testing/rules.md`.
- **Transition Action template** (LIVE: `SunsetClub`, `CloseClub`): one `DB::transaction` → `lockForUpdate` re-read → from-state guard throws `Illegal{Entity}Transition::cannotX($from)` → `update(['status'=>New])` (NEVER bump `version` — L3) → `record(Evt::NAME, Module::Parties->value, role, actorId, ENTITY_TYPE, (string)id, payload, [correlationId, causationId])`. Record AFTER update → payload carries post-transition `status`. **Signature rule:** threading params (`?int $causationId`, `?string $correlationId`) ONLY on the cascade-TARGET (`SunsetClub` alone). Standalone-only transitions — incl. cascade/supersession SOURCES (`RetireProducer`, `ActivateProducerAgreement`) that GENERATE linkage from `record()`'s return — use simpler `handle(int $id): Model`, omit linkage args → root event. So 3.1/3.2/4.1/4.2 = `handle(int $id): Model`. Action = SOLE status+event writer.
- **`domain_events.causation_id` = self-referencing FK to `domain_events.id`** → threading tests pass a REAL prior event id (record a root first, `sole()`, thread its `->id`(int)/`->correlation_id`(string)), never an arbitrary int (PG FK rejects).
- **Pint `{@see \FQCN}` → forced `use` import** (ONLY the fully-qualified form; unqualified cross-namespace/same-namespace `{@see}` left alone). Reference not-yet-built classes in PROSE only; re-run Pint to confirm import set stable. log.md via `memlog.sh` only; hot.md ≤550 words; APPROVED = human-only.
