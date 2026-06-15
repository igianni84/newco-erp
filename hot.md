---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 22:58 (ralph — `parties-producer-lifecycle` task 3.2 GREEN, committing on `ralph/parties-producer-lifecycle`).** Shipped `RetireProducer` + `ProducerRetired` (`active → retired`) — the **first cascade SOURCE**: records the root event, then sunsets every `active` operated Club via the constructor-injected `SunsetClub`, threading the root's `id`/`correlation_id`. Profile leg deferred (L6). PG17 verified.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 459/459 SQLite** (+4), **phpstan max 0**, **pint clean**, `openspec validate parties-producer-lifecycle --strict` ok. **PG17: 100/100** Parties (Feature+Unit) on `postgres:17` `:55432`. No migration, no composer drift, no arch-test amendment (`git diff main`).

## Active Change & Next Task
- **ACTIVE: `parties-producer-lifecycle`** (APPROVED, branch `ralph/parties-producer-lifecycle`). **6 of 10 done.**
- **Next = 4.1 `ActivateProducerAgreement`** — `Events/ProducerAgreementActivated` (`{producer_agreement_id, producer_id, club_id, status, supersedes}`; `supersedes`=prior id|null) + `Events/ProducerAgreementSuperseded` (`{…, superseded_by}`) + `Actions/ActivateProducerAgreement::handle(int $id): ProducerAgreement` (SIMPLE signature — supersession SOURCE records the derived event INLINE, nothing to inject). Body: `DB::transaction` → `lockForUpdate` → assert `Draft` else `IllegalProducerAgreementTransition::cannotActivate` → find prior `active` in SAME scope: `where('producer_id',…)` + **NULL-safe `club_id`** (`whereNull('club_id')` if this agreement's `club_id` is null, else `where('club_id',$clubId)`) `lockForUpdate` → if found set `Superseded` → set this `Active` → record `ProducerAgreementActivated` first (capture `$activated`, `supersedes`=prior?->id) → if prior, record `ProducerAgreementSuperseded` (`superseded_by`=this id, `causationId:$activated->id`, `correlationId:$activated->correlation_id`). New `tests/Feature/Modules/Parties/ProducerAgreementLifecycleTest.php`: (a) lone draft→Active, one Activated `supersedes` null, no Superseded; (b) two Producer-wide (`club_id` null) → A Superseded/B Active + payload pairing + causation; **(c) scope isolation: Producer-wide + `club_id=C` coexist, activate new `club_id=C` → only C-prior superseded, Producer-wide stays Active** (PG NULL-distinctness trap); non-draft throws. **VERIFY first:** `ProducerAgreement` model (`club_id` nullable, `producer_id`, `status` cast) + factory; `ProducerAgreementStatus{Draft,Active,Superseded,Terminated}`; `cannotActivate` + lang `producer_agreement.cannot_activate` (from 1.1). DB-touching → **PG17 mandatory** — `where('club_id',null)` silently matching nothing is THE live trap.
- Then 4.2 Terminate → 5.1 docs → 5.2 chain + cross-engine close. `ProducerActivated`+`ProducerRetired` (done) unblock `catalog-lifecycle-approval`.

## Blockers & Decisions Needed
- **None.** Seams ship ungated: KYC-on-activation → `parties-compliance`; all-members-gone-on-close → demand-side. Scope guard holds (supply-side only; no Customer/Account/Profile transition, no `originating_club_id` mutation). **Decision (3.2):** constructor-inject the cascade target (`SunsetClub`), not `app()`.

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin; misleading `NoTestCaseObjectOnCallStack` trace). PHPStan/Pint fine at default mem.
- **PG17 gate**: `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; bounded `docker exec pg pg_isready` wait, pace with in-container `sleep` (NO host sleep — blocked); `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest tests/…/Parties`; `docker rm -f pg`. Traps in `knowledge/testing/rules.md`.
- **Transition Action template** (LIVE ×4): `DB::transaction` → `lockForUpdate` → guard throws `Illegal{Entity}Transition::cannotX($from)` → `update(['status'=>New])` (NEVER bump `version`) → `record()` AFTER update (payload = post-transition). **Signature:** threading params ONLY on cascade-TARGET (`SunsetClub`); standalone + SOURCES = `handle(int $id): Model` (generate linkage from `record()`'s return). 4.1/4.2 = simple.
- **Cascade-source** (`RetireProducer`): constructor-inject the target Action; capture root `record()`; thread `id`(int)/`correlation_id`(string); ONE transaction (nested = savepoint = all-or-nothing); filter relation enum col with `->value`; idempotent via from-state filter. **No Mockery in suite** — atomicity via happy(all events+linked)+guard-reject(zero). 4.1 supersession is INLINE.
- **`causation_id` = self-FK to `domain_events.id`** → thread a REAL prior id. **Pint `{@see \FQCN}` → forced `use`** (leading-`\` only; unqualified left alone) — reference future classes in PROSE. log.md via `memlog.sh`; hot.md ≤550 words; APPROVED = human-only.
