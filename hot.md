---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 22:43 (ralph — `parties-producer-lifecycle` task 3.1 GREEN, committing on `ralph/parties-producer-lifecycle`).** Shipped `ActivateProducer` + `ProducerActivated` (`draft → active`), the first Producer transition. Near-twin of `CloseClub`: simple signature `handle(int $producerId): Producer`, root event, KYC gate deferred (L8 seam, DEC-071). DB-touching → PG17 verified.

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **Full suite 455/455 SQLite** (+4 vs 451), **phpstan max 0**, **pint clean**, `openspec validate parties-producer-lifecycle --strict` ok. **PG17 verified: 96/96** Parties (Feature+Unit) on `postgres:17` `:55432`. No migration, no composer drift, no arch-test amendment (verified `git diff main`).

## Active Change & Next Task
- **ACTIVE: `parties-producer-lifecycle`** (APPROVED, branch `ralph/parties-producer-lifecycle`). **5 of 10 tasks done.**
- **Next = 3.2 `RetireProducer` + `ProducerRetired` + Club-sunset cascade** — `Events/ProducerRetired` (`NAME='ProducerRetired'`, `ENTITY_TYPE='Producer'`, `payload(Producer)` → `{producer_id, status}`) + `Actions/RetireProducer::handle(int $producerId): Producer` (SIMPLE signature — it's a cascade SOURCE, GENERATES linkage, doesn't receive it). Body: `DB::transaction` → `lockForUpdate` re-read → assert `status === Active` (else `IllegalProducerTransition::cannotRetire`) → `update(['status'=>Retired])` → **capture** `$retired = record(ProducerRetired)` (root event) → walk `Producer::clubs()` (the 1.2 relation) for each `status === Active` Club, call `app(SunsetClub::class)->handle($club->id, causationId: $retired->id, correlationId: $retired->correlation_id)`. Clubs already `sunset`/`closed` skipped (idempotent). NO Profile leg (deferred — L6). Whole cascade ONE transaction (rolls back together). Extend `ProducerLifecycleTest.php`. **First mixed-Club-state fixture** (2 active + 1 closed); assert one `ProducerRetired` + exactly two `ClubSunset` whose `causation_id === $retired->id` and shared `correlation_id`; closed Club untouched; non-`active` retire throws + emits nothing. DB-touching → **must verify PG17**.
- Then: 4.1 ActivateProducerAgreement (NULL-safe supersession — PG NULL-distinctness trap, assert scope isolation on PG17) → 4.2 Terminate → 5.1 docs → 5.2 chain+cross-engine close.
- Emits `ProducerActivated` (done 3.1) + `ProducerRetired` (3.2) → unblocks `catalog-lifecycle-approval`.

## Blockers & Decisions Needed
- **None.** Two documented seams ship ungated (tightened later): KYC-on-activation → `parties-compliance` (now LIVE & documented in `ActivateProducer`); all-members-gone-on-close → demand-side (documented in `CloseClub`). Scope guard holds: supply-side transitions only; no Customer/Account/Profile transition, no `originating_club_id` mutation. **Decision (3.1):** leave the status-enum docblocks (`ProducerStatus`/`ClubStatus`) untouched — their "writes NO transition" note is a `parties-core`-era frozen statement; 2.1/2.2 left `ClubStatus` alone, so 3.x mirrors that.

## Open Patterns
- **Full-suite runner = `php -d memory_limit=512M vendor/bin/pest`** — NOT `php artisan test` (128M OOMs in arch plugin; crash masquerades as `NoTestCaseObjectOnCallStack`/`stream_filter_remove` shutdown trace, real cause "memory exhausted" at head). PHPStan + Pint fine at default mem.
- **PG17 gate** (every DB-touching task): `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; wait via bounded `docker exec pg pg_isready` loop, pacing with in-container `sleep` (NO host foreground sleep — it's blocked); `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest tests/…/Parties`; `docker rm -f pg`. Five SQLite↔PG traps in `knowledge/testing/rules.md`.
- **Transition Action template** (LIVE: `SunsetClub`, `CloseClub`, `ActivateProducer`): one `DB::transaction` → `lockForUpdate` re-read → from-state guard throws `Illegal{Entity}Transition::cannotX($from)` → `update(['status'=>New])` (NEVER bump `version` — L3) → `record(Evt::NAME, Module::Parties->value, role, actorId, ENTITY_TYPE, (string)id, payload, [correlationId, causationId])`. Record AFTER update → payload carries post-transition `status`. **Signature rule:** threading params (`?int $causationId`, `?string $correlationId`) ONLY on the cascade-TARGET (`SunsetClub` alone). Standalone-only transitions — incl. cascade/supersession SOURCES (`RetireProducer`, `ActivateProducerAgreement`) that GENERATE linkage from `record()`'s return — use simpler `handle(int $id): Model`, omit linkage args → root event. So 3.2/4.1/4.2 = `handle(int $id): Model`. Action = SOLE status+event writer.
- **`domain_events.causation_id` = self-referencing FK to `domain_events.id`** → threading tests pass a REAL prior event id (record a root first, `sole()`, thread its `->id`(int)/`->correlation_id`(string)), never an arbitrary int (PG FK rejects).
- **Pint `{@see \FQCN}` → forced `use` import** (ONLY the fully-qualified form; unqualified cross-namespace/same-namespace `{@see}` left alone — that's how `ProducerActivated` cites `{@see ActivateProducer}` un-imported). Reference not-yet-built classes (e.g. `RetireProducer` before 3.2) in PROSE only; re-run Pint to confirm import set stable. log.md via `memlog.sh` only; hot.md ≤550 words; APPROVED = human-only.
