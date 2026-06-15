---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 17:39 (ralph iteration 6/20 — `parties-core` task 3.1 Club DONE).** Built the FIRST Parties entity with **a foreign key** (`producer_id`→`parties_producers`, within Module K) **and a Money field** (`fee` via `MoneyCast`). Eight files: migration `parties_clubs` (FK `->constrained(table:, indexName:'parties_clubs_producer_fk')`, RESTRICT; `fee_minor` int + `fee_currency` str(3) nullable; `status` + `registration_flow_type` each string + driver-guarded PG CHECK — status defaulted `active`, flow **no default**; `generates_credit`/`invite_only` bools; `version`); `Models\Club` (`belongsTo` Producer; `fee→MoneyCast`); `ClubFactory` (`producer_id=>Producer::factory()`); `Events\ClubCreated` (fee via `toPayload()`); `Exceptions\MissingClubProducer` + NEW `lang/en/parties.php`; `Actions\CreateClub` (presence pre-check → insert `active` → record). **6 of 11 done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **Branch `ralph/parties-core`.** Pint ✅ · filtered 7/40 ✅ · full **396/1460 on SQLite AND PostgreSQL 17** ✅ · phpstan max 0 ✅ · pint --test ✅ · `openspec validate … --strict` ✅ · composer diff empty ✅. **PG constraints proven directly:** `status='frozen'`, `registration_flow_type='walk_in'`, `producer_id=999` each REJECTED (status_check / flow_check / producer_fk); the all-valid row inserts.

## Active Change & Next Task
- **`parties-core`** (capability `party-registry`; 11 tasks; 5 `*Created` events; 7 entities). APPROVED present. 6/11 done.
- **Next: task 3.2 — ProducerAgreement** (DB task → **PG17 gate applies**; **two** FKs). Migration `parties_producer_agreements` (long table → 63-char limit BITES → use SHORT FK names, e.g. `parties_pa_producer_fk` / `parties_pa_club_fk`): `producer_id` FK (required), `club_id` FK→`parties_clubs` **nullable**, `status` string+CHECK+`ProducerAgreementStatus` cast default `draft`, `term_start`/`term_end` date nullable, `settlement_cadence` string nullable, `version`, `timestampsTz`. `Models\ProducerAgreement` (`belongsTo` Producer + optional `belongsTo` Club). `ProducerAgreementFactory`. `Events\ProducerAgreementCreated` (payload `producer_id` + `club_id` nullable, by key). New localized missing-Producer exception (add a key to the now-existing `lang/en/parties.php`). `Actions\CreateProducerAgreement`: reject missing producer → insert `draft` → record (one tx). **Single-active-per-scope is an ACTIVATION rule — NOT enforced here; drafts create freely.** Tests: one Producer-wide draft + one Club-narrowed draft BOTH succeed; missing-producer throws; event by key.

## Blockers & Decisions Needed
- **None.** No open ADR gate hit here.
- Asymmetries to keep: Supplier & Account emit **no** `*Created`; markers/classifiers take **no default**; Originating Club = field only (born NULL, no setter).

## Open Patterns
- **FK idiom** (worked 3.1): `foreignId()->constrained(table:, indexName:'<short>')`, RESTRICT default (a *referenced* parent; `->cascadeOnDelete()` only for an *owned* child); short names for the 63-char limit; within-module `belongsTo` allowed + arch-green.
- **MoneyCast** (worked 3.1; Club is first user): `integer('x_minor')->nullable()` + `string('x_currency',3)->nullable()`; assert raw cols via `->value()`, payload via `toEqual(Money::toPayload())` (jsonb + PHPStan-max safe).
- **Spine DB-entity template** + **minimal entity** (Supplier) + **marker/classifier = no `->default()`** + **`{@see}`-import trap** (plain prose for docblock-only classes) + **PII-free `*Created` payloads** all hold. `lang/en/parties.php` group file exists for localized rejections.
- **PG17 gate** at every DB task — docker `postgres:17` (55432) + `pg_isready` poll + `DB_CONNECTION=pgsql … php artisan test`; cross-engine close at 6.2. **log.md via `scripts/memlog.sh` only; hot.md ≤550 words; APPROVED = human-only.**
