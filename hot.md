---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 22:17 (ralph — `parties-producer-lifecycle` task 1.2 GREEN, committing on `ralph/parties-producer-lifecycle`).** Added the within-module `Producer::clubs(): HasMany` — the read the retirement cascade (3.2) walks, inverse of the existing `Club::producer()`. First DB-touching task → verified on PG17. Still no transition Action on disk; those start at 2.1.

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **Full suite 444/444 SQLite** (+3 vs the 441 baseline), **phpstan max 0**, **pint clean**, `openspec validate parties-producer-lifecycle --strict` ok. **PG17 verified: 85/85** Parties (Feature+Unit) on `postgres:17` `:55432`. No migration, no composer drift, no arch-test amendment (verified `git diff main`).

## Active Change & Next Task
- **ACTIVE: `parties-producer-lifecycle`** (APPROVED, branch `ralph/parties-producer-lifecycle`). **2 of 10 tasks done.**
- **Next = 2.1 `SunsetClub` + `ClubSunset`** — `Events/ClubSunset` (`NAME='ClubSunset'`, `ENTITY_TYPE='Club'`, `payload(Club)` → `{club_id, producer_id, status}`) + `Actions/SunsetClub::handle(int $clubId, ?int $causationId=null, ?string $correlationId=null): Club`: one `DB::transaction`, `lockForUpdate` re-read, assert `status===Active` (else `IllegalClubTransition::cannotSunset`), set `Sunset`, `record('ClubSunset', …)` threading causation/correlation. SunsetClub is the SOLE `ClubSunset` writer (cascade reuses it). DB-touching → **must verify PG17**.
- Then: 2.2 CloseClub → 3.1 ActivateProducer → 3.2 RetireProducer+cascade (walks `Producer::clubs()`) → 4.1 ActivateProducerAgreement (NULL-safe supersession) → 4.2 Terminate → 5.1 docs → 5.2 chain+cross-engine close.
- Emits `ProducerActivated`/`ProducerRetired` (3.x) → unblocks `catalog-lifecycle-approval`.

## Blockers & Decisions Needed
- **None.** Two documented seams ship ungated (tightened later): KYC-on-activation → `parties-compliance`; all-members-gone-on-close → demand-side. Scope guard holds: supply-side transitions only; no Customer/Account/Profile transition, no `originating_club_id` mutation.

## Open Patterns
- **Full-suite runner = `php -d memory_limit=512M vendor/bin/pest`** — NOT `php artisan test` (128M OOMs in the arch plugin's parser; crash masquerades as a `NoTestCaseObjectOnCallStack`/`stream_filter_remove` shutdown trace — real cause "memory exhausted" at the head). PHPStan + Pint fine at default mem.
- **PG17 gate** (every DB-touching task): `docker run -d --name pg … -p 55432:5432 postgres:17`; wait via a bounded `pg_isready` probe loop (NO foreground `sleep`); `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 … php -d memory_limit=512M vendor/bin/pest tests/.../Parties`; `docker rm -f`. Five SQLite↔PG traps in `knowledge/testing/rules.md` (NUL-in-anon-class, uuid strictness, jsonb key-reorder, timestamptz `+00`, trigger aborts whole tx).
- **Within-module inverse `hasMany`:** same-namespace related model needs NO import (`Club::class` resolves); type `@return HasMany<Club,$this>` + `@property-read Collection<int,Club>` → PHPStan max clean; boundary-clean (arch ban is cross-module only).
- **Pint `fully_qualified_strict_types` turns `{@see \FQCN}` into a real `use` import** — reference a not-yet-built class (later-task Action) in PROSE only, `{@see}` only existing symbols; re-run Pint to confirm import set stable.
- **Transition exception shape** (1.1, ready for 2.x): `Illegal{Entity}Transition extends RuntimeException` + `::cannotX({Status} $from)` factories resolving `__('parties.<group>.<key>', ['state'=>$from->value])`. **Carry:** transition = single-purpose Action, sole `status` writer, from-state guard via `lockForUpdate` re-read; `record()` returns the `DomainEvent` (use `->id` int causationId, `->correlation_id` string correlationId); `SpineCreationChainTest` stays green unamended. log.md via `memlog.sh` only; hot.md ≤550 words; APPROVED = human-only.
