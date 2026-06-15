---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 23:21 (ralph — `parties-producer-lifecycle` task 4.2 GREEN, committing on `ralph/parties-producer-lifecycle`).** Shipped `TerminateProducerAgreement` (`active → terminated`) + `ProducerAgreementTerminated` — the SIMPLEST transition (twin of `CloseClub`/`ActivateProducer`: standalone, no supersession, no cascade, **NO Producer cascade** per § 4.6.1). **4-key linkage-free payload.** Template ported verbatim — no new pattern. **The ProducerAgreement FSM is now complete** (activate/supersede/terminate).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 470/470 SQLite** (+4), **phpstan max 0**, **pint clean**, `openspec validate parties-producer-lifecycle --strict` ok. **PG17: 111/111** Parties (Feature+Unit) on `postgres:17` `:55432`. No migration, no composer drift, no arch-test amendment (`git diff main`).

## Active Change & Next Task
- **ACTIVE: `parties-producer-lifecycle`** (APPROVED, branch `ralph/parties-producer-lifecycle`). **8 of 10 done.** All six transition Actions + seven events shipped; only docs + the integration test remain.
- **Next = 5.1 `Docs — CONTEXT.md terms + Parties lifecycle contract note`** (proposal Impact) — **DOCS ONLY, no code, no PG17.** (1) Extend `CONTEXT.md` (NOT protected — it's the glossary of record) with the resolved supply-side lifecycle terms: Club **sunset**/**close** (`active→sunset→closed`), Producer **retire** (`draft→active→retired`, cascades Club sunset), agreement **supersede**/**terminate** (`draft→active→superseded|terminated`), and the **`(producer_id, club_id)` agreement scope** (NULL `club_id` = distinct Producer-wide) — each with verbatim spec anchors (§ 4.3/4.4/4.6/4.6.1, § 15.3/15.4/15.5, BR-K-Agreement-1/3). (2) Add a Parties **contract note** listing the **seven** PII-free event payloads + the **two deferred seams**. Seven events: `ProducerActivated`/`ProducerRetired`→`{producer_id,status}`; `ClubSunset`/`ClubClosed`→`{club_id,producer_id,status}`; `ProducerAgreementActivated`→`{…,supersedes}`; `ProducerAgreementSuperseded`→`{…,superseded_by}`; `ProducerAgreementTerminated`→`{producer_agreement_id,producer_id,club_id,status}`. Seams: KYC-on-activation→`parties-compliance` (DEC-071); all-members-gone-on-close→demand-side. **Verify quality cmds green** + `openspec validate --strict` + terminology re-read vs spec. Note the `producer_agreement_id`-vs-creation's-`agreement_id` divergence (design L4).
- Then 5.2 full chain + cross-engine close (one feature test: create→activate→renew/supersede→sunset/close→retire-cascade; assert demand-side inert; whole Parties suite on PG17). `ProducerActivated`+`ProducerRetired` (done) unblock `catalog-lifecycle-approval`.

## Blockers & Decisions Needed
- **None.** Seams ship ungated (KYC→`parties-compliance`; all-members-gone→demand-side). Scope guard holds (supply-side only). Transition events use `producer_agreement_id` (design L4); creation's `agreement_id` left unchanged — deliberate, to document in 5.1.

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin; misleading `NoTestCaseObjectOnCallStack` trace). PHPStan/Pint fine at default mem.
- **PG17 gate** (DB-touching tasks only; 5.1 docs-only → skip): `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; bounded `docker exec pg pg_isready` wait, in-container `sleep` (NO host sleep — blocked); `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest tests/…/Parties`; `docker rm -f pg`. Traps in `knowledge/testing/rules.md`.
- **Transition Action template** (LIVE ×6, FSMs complete): `DB::transaction` → `lockForUpdate` → guard throws `Illegal{Entity}Transition::cannotX($from)` → `update(['status'=>New])` (NEVER bump `version`) → `record()` AFTER update (payload = post-transition). **Signature:** threading params ONLY on cascade-TARGET (`SunsetClub`); standalone + SOURCES = `handle(int $id): Model`. Cascade-source (`RetireProducer`): constructor-inject target. Inline derived-source (`ActivateProducerAgreement`): record derived event via recorder inline, NULL-safe scope branch (`whereNull` vs `where`).
- **`causation_id` = self-FK to `domain_events.id`** → thread a REAL prior id. **Pint `{@see \FQCN}` → forced `use`** (leading-`\` only; unqualified left alone) — reference future/cross-ns classes UNQUALIFIED. Pin payload key set via `toEqualCanonicalizing`. log.md via `memlog.sh`; hot.md ≤550 words; APPROVED = human-only.
