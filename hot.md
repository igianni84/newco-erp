---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 22:08 (ralph — `parties-producer-lifecycle` task 1.1 GREEN, committing on `ralph/parties-producer-lifecycle`).** First task of the supply-side lifecycle slice: the three transition-guard exceptions + their localized copy — the foundation the transition Actions (2.x–4.x) throw from. No DB touched yet (pure exceptions + translator), so no transitions/events exist on disk; those start at task 1.2.

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **Full suite 441/441 SQLite** (+13 vs the 428 baseline), **phpstan max 0**, **pint clean**, `openspec validate parties-producer-lifecycle --strict` ok. No migration, no composer drift (verified `git diff main`). PG17 NOT run for 1.1 (no DB-touching code); required from 1.2 onward.

## Active Change & Next Task
- **ACTIVE: `parties-producer-lifecycle`** (APPROVED, branch `ralph/parties-producer-lifecycle`). **1 of 10 tasks done.**
- **Next = 1.2 `Producer::clubs()` within-module `HasMany`** (the read the retire→sunset cascade walks). FIRST DB-touching task → **must verify on PG17** (`php -d memory_limit=512M vendor/bin/pest`, docker `postgres:17` :55432). Typed generic `@return HasMany<Club, $this>`; arch tests stay green unamended.
- Then: 2.1 SunsetClub/ClubSunset → 2.2 CloseClub → 3.1 ActivateProducer → 3.2 RetireProducer+cascade → 4.1 ActivateProducerAgreement (NULL-safe supersession) → 4.2 Terminate → 5.1 docs → 5.2 chain+cross-engine close.
- Emits `ProducerActivated`/`ProducerRetired` (3.x) → unblocks `catalog-lifecycle-approval`.

## Blockers & Decisions Needed
- **None.** Two documented seams ship ungated (tightened later): KYC-on-activation → `parties-compliance`; all-members-gone-on-close → demand-side. Scope guard holds: this slice adds ONLY supply-side transitions; no Customer/Account/Profile transition, no `originating_club_id` mutation.

## Open Patterns
- **Full-suite runner = `php -d memory_limit=512M vendor/bin/pest`** — NOT `php artisan test` (128M OOMs in the arch plugin's `nikic/php-parser` scan; crash masquerades as a `NoTestCaseObjectOnCallStack`/`stream_filter_remove` shutdown trace — real cause is "memory exhausted" at the head). PHPStan + Pint fine at default mem.
- **Translator-only unit tests must `uses(Tests\TestCase::class)`** (no `RefreshDatabase`) — `tests/Pest.php` binds TestCase to `Feature` only; `tests/Unit/**` won't boot Laravel otherwise, so `__()` returns the bare key.
- **PHPStan max rejects Pest closure-dataset typing** (`$factory()`→mixed, `toBeInstanceOf(string)` wants class-string) — assert factory outputs with explicit per-case `it()` blocks, not a `->with([Closure,...])` dataset.
- **Transition exception shape:** `Illegal{Entity}Transition extends RuntimeException` (non-final, like the creation guards) + named `::cannotX({Entity}Status $from)` factories resolving `__('parties.<group>.<key>', ['state'=>$from->value])`; `:state` = enum backing value, not PII. ADD lang keys to existing groups, don't replace.
- **Carry (next tasks):** transition = single-purpose Action, sole writer of `status`, from-state guard via `lockForUpdate` re-read; cascade synchronous in-Action (one transaction); supersession app-enforced, scope `(producer_id, club_id)` NULL-safe (`whereNull` Producer-wide; partial-unique rejected — `NULLS NOT DISTINCT` not SQLite-portable); `record()` returns the `DomainEvent` for causation/correlation threading. `SpineCreationChainTest` stays green unamended. log.md via `memlog.sh` only; hot.md ≤550 words; APPROVED = human-only.
