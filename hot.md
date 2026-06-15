---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 23:13 (ralph — `parties-producer-lifecycle` task 4.1 GREEN, committing on `ralph/parties-producer-lifecycle`).** Shipped `ActivateProducerAgreement` (`draft → active`) + `ProducerAgreementActivated`/`ProducerAgreementSuperseded` — scope-aware, **NULL-safe** BR-K-Agreement-1 supersession over `(producer_id, club_id)`. First **inline derived-event source** (supersession has no Action → recorded via recorder directly). PG17 verified both NULL-trap directions.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 466/466 SQLite** (+7), **phpstan max 0**, **pint clean**, `openspec validate parties-producer-lifecycle --strict` ok. **PG17: 107/107** Parties (Feature+Unit) on `postgres:17` `:55432`. No migration, no composer drift, no arch-test amendment (`git diff main`).

## Active Change & Next Task
- **ACTIVE: `parties-producer-lifecycle`** (APPROVED, branch `ralph/parties-producer-lifecycle`). **7 of 10 done.**
- **Next = 4.2 `TerminateProducerAgreement`** — the SIMPLEST template (twin of `CloseClub`/`ActivateProducer`; standalone, no supersession, no cascade). New `Events/ProducerAgreementTerminated` (`final`, `NAME='ProducerAgreementTerminated'` verbatim § 15.5, `ENTITY_TYPE='ProducerAgreement'`, `payload(ProducerAgreement)` → `{producer_agreement_id, producer_id, club_id, status}` — **4-key, NO linkage arg**; use `producer_agreement_id` key per design L4, NOT creation's `agreement_id`) + `Actions/TerminateProducerAgreement::handle(int $id): ProducerAgreement` (simple signature): `DB::transaction` → `lockForUpdate` → assert `=== Active` else `IllegalProducerAgreementTransition::cannotTerminate` (EXISTS, 1.1) → `update → Terminated` → `record(ProducerAgreementTerminated)` (no causation/correlation → root). **NO cascade to Producer state** (§ 4.6.1) — document in docblock. Add cases to `tests/Feature/Modules/Parties/ProducerAgreementLifecycleTest.php`: active→Terminated + one root event + Producer unchanged; reject-from-draft; reject-from-superseded/terminated. **VERIFY first:** `cannotTerminate` factory + lang `producer_agreement.cannot_terminate` (both ship from 1.1, confirmed in lang/en/parties.php). DB-touching → **PG17 mandatory** (lighter risk than 4.1 — no NULL trap).
- Then 5.1 docs (CONTEXT.md terms + contract note) → 5.2 full chain + cross-engine close. `ProducerActivated`+`ProducerRetired` (done) unblock `catalog-lifecycle-approval`.

## Blockers & Decisions Needed
- **None.** Seams ship ungated (KYC→`parties-compliance`; all-members-gone→demand-side). Scope guard holds (supply-side only). **Decision (4.1):** transition events use `producer_agreement_id` (design L4) — DIVERGES from creation's `agreement_id` (archived, unchanged); deliberate, documented for 5.1 docs.

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin; misleading `NoTestCaseObjectOnCallStack` trace). PHPStan/Pint fine at default mem.
- **PG17 gate**: `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; bounded `docker exec pg pg_isready` wait, pace with in-container `sleep` (NO host sleep — blocked); `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest tests/…/Parties`; `docker rm -f pg`. Traps in `knowledge/testing/rules.md`.
- **Transition Action template** (LIVE ×5): `DB::transaction` → `lockForUpdate` → guard throws `Illegal{Entity}Transition::cannotX($from)` → `update(['status'=>New])` (NEVER bump `version`) → `record()` AFTER update (payload = post-transition). **Signature:** threading params ONLY on cascade-TARGET (`SunsetClub`); standalone + SOURCES = `handle(int $id): Model`. 4.2 = simplest (no derived event).
- **Inline derived source** (`ActivateProducerAgreement` 4.1): same-entity supersession recorded INLINE (no injected Action) — capture root activation, supersede prior BEFORE self BEFORE recording, thread `causationId:$activated->id`/`correlationId:$activated->correlation_id`. **NULL-safe scope:** `where('club_id', null)` binds `= NULL` (never matches) → branch `whereNull` vs `where`; SQLite-green hides it, PG17 catches it. Cascade-source (`RetireProducer`): constructor-inject target; ONE transaction = savepoint = all-or-nothing; filter relation enum with `->value`. No Mockery — atomicity via happy(all linked)+guard-reject(zero).
- **`causation_id` = self-FK to `domain_events.id`** → thread a REAL prior id. **Pint `{@see \FQCN}` → forced `use`** (leading-`\` only; unqualified left alone) — reference future classes in PROSE. Pin payload key set via `toEqualCanonicalizing`. log.md via `memlog.sh`; hot.md ≤550 words; APPROVED = human-only.
