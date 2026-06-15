---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 17:50 (ralph iteration 7/20 — `parties-core` task 3.2 ProducerAgreement DONE).** Built the NewCo↔Producer agreement (§ 4.6, DEC-070): the FIRST Parties entity with **two FKs** (`producer_id` required + `club_id` **nullable**) and the FIRST with **date** columns. Eight files: migration `parties_producer_agreements` (two `->constrained(table:, indexName:'parties_pa_producer_fk'|'parties_pa_club_fk')`, RESTRICT; the second `->nullable()`; `status` string + PG CHECK default `draft`; `term_start`/`term_end` `date()->nullable()`; `settlement_cadence` str nullable [D19, no CHECK]; `version`); `Models\ProducerAgreement` (two within-module `belongsTo`; `term_*→immutable_date`); `ProducerAgreementFactory` (`club_id=>null`, fixed-literal dates); `Events\ProducerAgreementCreated` (§15.5; dates ISO via `?->toDateString()`); `Exceptions\MissingAgreementProducer` + NEW `producer_agreement` group in `lang/en/parties.php`; `Actions\CreateProducerAgreement` (producer pre-check → insert `draft` → record; **single-active NOT enforced**). **7 of 11 done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **Branch `ralph/parties-core`.** Pint ✅ · filtered 8/44 ✅ · full **404/1504 on SQLite AND PostgreSQL 17** ✅ · phpstan max 0 ✅ · pint --test ✅ · `openspec validate … --strict` ✅ · composer diff empty ✅. **PG constraints proven directly:** `status='void'` / `producer_id=999` / `club_id=999` each REJECTED (status_check / parties_pa_producer_fk / parties_pa_club_fk); both valid rows (Producer-wide null-club, Club-narrowed) insert.

## Active Change & Next Task
- **`parties-core`** (capability `party-registry`; 11 tasks; 5 `*Created` events; 7 entities). APPROVED present. 7/11 done.
- **Next: task 4.1 — Customer + Account** (DB task → **PG17 gate applies**; the BIGGEST task — TWO tables, co-provisioning, strict PII guard). Migrations `parties_customers` (`email` **unique**, `name`, `phone?`, `date_of_birth` `date()->nullable()` [use the `immutable_date` cast — worked at 3.2], `party_type` str+CHECK+cast **no default** [marker, action sets `customer`], `preferred_currency`, `preferred_locale`, `status` str+CHECK+`CustomerStatus` default `pending`, `originating_club_id` **nullable** FK→`parties_clubs` [short name e.g. `parties_cust_oc_fk`], `version`) + `parties_accounts` (`customer_id` FK, `account_type` str+CHECK+cast default `personal`, `name` default `'Personal'`, `status` str+CHECK+`AccountStatus` default `active`, `default_currency`, `version`). Models `Customer` (`hasOne` Account; optional `belongsTo` originating Club) + `Account` (`belongsTo` Customer). `Events\CustomerCreated` (**PII-free** — omit name/email/phone/DOB; pin via `array_keys()->toEqualCanonicalizing`). `Exceptions\DuplicateCustomerEmail` (new `customer` lang group). `Actions\CreateCustomer`: one tx — email pre-check → insert Customer (`pending`, `customer`, OC NULL) → provision 1:1 Account (`active`,`personal`) → record **only** `CustomerCreated` (**no `AccountCreated`** — Account is event-silent, NOT minimal). **NO `originating_club_id` setter.**

## Blockers & Decisions Needed
- **None.** No open ADR gate hit here.
- Asymmetries to keep: Supplier & Account emit **no** `*Created`; markers/classifiers take **no default**; Originating Club = field only (born NULL, no setter).

## Open Patterns
- **FK idiom** (3.1 + two-FK at 3.2): `foreignId()->constrained(table:, indexName:'<short>')`, RESTRICT default; optional FK = `->nullable()->constrained(...)`; optional `belongsTo` same `@return BelongsTo<X,$this>` (+ `@property X|null`).
- **Date columns** (3.2; reused 4.1 DOB): `date()->nullable()` + `'immutable_date'` cast + `@property CarbonImmutable|null`; PG `date` returns plain `Y-m-d` (trap 4 N/A); assert via `?->toDateString()`.
- **MoneyCast** (3.1): `integer('x_minor')->nullable()`+`string('x_currency',3)->nullable()`; assert raw via `->value()`, payload via `toEqual(Money::toPayload())`.
- **Spine DB template** + marker/classifier = **no `->default()`** + minimal entity (Supplier) + **event-silence** (Supplier; Account leg of 4.1) + **PII-free `*Created`** (pin key-set via `array_keys()->toEqualCanonicalizing`) all hold. `lang/en/parties.php` is the shared rejection-copy home (`club`, `producer_agreement`; next `customer`/`profile`).
- **PG17 gate** every DB task — docker `postgres:17` (55432) + `pg_isready` poll + `DB_CONNECTION=pgsql … php artisan test`; cross-engine close at 6.2. **log.md via `scripts/memlog.sh` only; hot.md ≤550 words; APPROVED = human-only.**
