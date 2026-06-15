---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 18:14 (ralph iteration 8/20 — `parties-core` task 4.1 Customer + Account DONE).** The BIGGEST spine task: TWO tables + the FIRST multi-table action (co-provisioning) + the FIRST globally-unique column + the strict PII-free event + the Originating-Club seam. Ten new files: migrations `parties_customers` (`email` **unique**; `name`/`phone?`/`date_of_birth` date?; `party_type` str **no-default** + CHECK [marker]; `preferred_currency`/`preferred_locale` plain str **no cast/CHECK**; `status` str + CHECK default `pending`; `originating_club_id` **nullable** FK→clubs `parties_customers_oc_fk` born NULL; `version`) + `parties_accounts` (`customer_id` FK; `account_type` str+CHECK default `personal`; `name` default `'Personal'`; `status` str+CHECK default `active`; `default_currency` plain str; **NO balance/credit/payment-provider col**); `Models\{Customer (hasOne Account + optional belongsTo originating Club), Account (belongsTo Customer)}`; `{CustomerFactory,AccountFactory}`; `Events\CustomerCreated` (PII-free — omits email/name/phone/DOB); `Exceptions\DuplicateCustomerEmail` (email NOT echoed — PII; new `customer` lang group); `Actions\CreateCustomer` (one tx: email pre-check → insert Customer → co-provision Account via `$customer->account()->create()` → record **only** `CustomerCreated`; **no OC setter**). **8 of 11 done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **Branch `ralph/parties-core`.** Pint ✅ · filtered 12/72 ✅ · full **416/1576 on SQLite AND PostgreSQL 17** ✅ · phpstan max 0 ✅ · pint --test ✅ · `openspec validate … --strict` ✅ · composer diff empty ✅. **PG constraints proven directly:** all 7 forbidden inserts rejected by the right named constraints (`parties_customers_party_type_check`/`_status_check`/`_oc_fk`/`_email_unique`, `parties_accounts_account_type_check`/`_status_check`/`_customer_fk`); valid rows insert. **PG full-suite needs `php -d memory_limit=512M vendor/bin/pest`** (Arch-plugin OOM + `laravel/pao` shutdown fatal are runner artifacts — see knowledge/testing).

## Active Change & Next Task
- **`parties-core`** (capability `party-registry`; 11 tasks; 5 `*Created` events; 7 entities). APPROVED present. 8/11 done.
- **Next: task 5.1 — Profile** (DB → **PG17 gate**). Migration `parties_profiles` (`customer_id` FK + `club_id` FK [both within-module]; `state` [NOT `status`] str + CHECK + `ProfileState` cast default `applied`; `tier?`/`role?`; `invited_by_customer_id` unsignedBigInteger nullable; `version`) **+ a partial unique index** `(customer_id, club_id) WHERE state NOT IN ('rejected','cancelled','inactive')` via raw `DB::statement` (**verify portability on PG17 AND SQLite** — design D8 open question). Model `Profile` (`belongsTo` Customer + Club). `Events\ProfileCreated`. `Exceptions\DuplicateProfileForClub` (new `profile` lang group). `Actions\CreateProfile`: require customer_id + club_id → non-terminal-duplicate pre-check (localized reject) → insert `applied` → record `ProfileCreated`. Test: one Customer + three Clubs → 3 Profiles; duplicate (customer,club) rejected by app guard AND the partial index (raw-dup insert in a **savepoint** — trap 5).

## Blockers & Decisions Needed
- **None.** D8 open question (partial-unique portability) is resolved AT task 5.1 by verifying both engines; fallback = app guard + plain composite index if a gap surfaces.
- Asymmetries holding: Supplier & Account emit **no** `*Created`; markers/classifiers + preference-strings take **no default/CHECK** the way `status` does; Originating Club = field only (born NULL, no setter).

## Open Patterns
- **Co-provisioning** (4.1, first multi-table action): insert parent → `relation()->create()` child → record only the parent `*Created`; child relies on column defaults (`name`/`version`). `hasOne` = `@return HasOne<X,$this>`+`@property-read X|null`; optional `belongsTo` = `@return BelongsTo<X,$this>`+`@property-read X|null`.
- **Preference strings** (currency/locale): plain `string`, NO cast/CHECK; fail-closed at the **action** via typed `Currency`/`SupportedLocale` → store `->value`. **Unique factory col:** `(string) fake()->unique()->safeEmail()`.
- **`{@see}`-import trap (sharpened):** only FQN `{@see \A\B}` makes Pint import; bare `{@see Short}`/backtick-prose doesn't. Migration imports only code-used enums; model imports its action+event for `{@see}`; exception imports only `RuntimeException`. **PII in rejection msg:** omit it (email = PII; logs).
- **FK/date/Money/marker idioms** + **PG17 gate** every DB task all hold. `lang/en/parties.php` = shared rejection copy (`club`/`producer_agreement`/`customer`; next `profile`). **log.md via `memlog.sh` only; hot.md ≤550 words; APPROVED = human-only.**
