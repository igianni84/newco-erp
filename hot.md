---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 18:31 (ralph iteration 9/20 — `parties-core` task 5.1 Profile DONE).** The membership in one Club (Netflix-style Customer↔Profile; NO Membership entity). FIRST Parties entity with a **partial unique index** — and the change's one open question (D8), now **RESOLVED**. 7 new files: migration `parties_profiles` (`customer_id` FK + `club_id` FK both REQUIRED; `state` str + CHECK + `ProfileState` cast default `applied` [column is `state`, NOT `status`]; `tier`/`role` nullable; `invited_by_customer_id` `unsignedBigInteger` nullable — a **non-FK referral seam**; `version`) **+ a partial UNIQUE index** `parties_profiles_customer_club_nonterminal_unique` `(customer_id, club_id) WHERE state NOT IN ('rejected','cancelled','inactive')` via raw `DB::statement`, created on BOTH engines; `Models\Profile` (two within-module `belongsTo`); `ProfileFactory`; `Events\ProfileCreated` (PII-free); `Exceptions\DuplicateProfileForClub` (echoes :customer/:club ids — not PII; new `profile` lang group); `Actions\CreateProfile` (one tx: non-terminal-dup pre-check `whereNotIn` 3 terminal tokens [mirrors the index] → insert `applied` → record `ProfileCreated`). **9 of 11 done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **Branch `ralph/parties-core`.** Pint ✅ · filtered 8/42 ✅ · full **424/1618 on SQLite** ✅ · **47/47 Parties subset on PostgreSQL 17** ✅ · phpstan max 0 ✅ · pint --test ✅ · `openspec validate … --strict` ✅ · composer diff empty ✅. **PG constraints proven directly:** duplicate-live REJECTED by the partial unique index; `state='pending'` by `_state_check`; bad customer/club by the two FKs; **two `rejected` rows for one pair BOTH land** (partial predicate permits terminal dups — the D8 proof). Full-suite gate now needs `php -d memory_limit=512M vendor/bin/pest` on BOTH engines (the pao teardown fatal hits SQLite too at this scale; Parties subset stays clean under `artisan test`).

## Active Change & Next Task
- **`parties-core`** (capability `party-registry`; 11 tasks; 5 `*Created` events; 7 entities). APPROVED present. 9/11 done.
- **Next: task 6.1 — Docs (DOCS-ONLY, no code; run lint/format).** Extend `CONTEXT.md` (root) with the resolved Parties spine glossary (Profile [the membership, no Membership entity], Supplier [minimal Party subtype], Club, Account [billing container, not a ledger], ProducerAgreement, party-type marker [marker-on-subtype], Originating-Club field [born NULL, locked at first approval — deferred]) + a Parties event-contract note: the **five** `*Created` payload shapes (PII-free; `CustomerCreated` omits name/email/phone/DOB; `ClubCreated` fee as `{minor_units, currency}`) and the **deliberate Supplier/Account event silence**. Then 6.2 — full-chain integration + cross-engine close.

## Blockers & Decisions Needed
- **None.** D8 (partial-unique portability) is RESOLVED at 5.1 — portable on both engines, no fallback. Asymmetries holding: Supplier & Account emit **no** `*Created`; markers/classifiers + preference-strings take **no default/CHECK** the way `status` does; Originating Club = field only (born NULL, no setter).

## Open Patterns
- **Partial unique index for live-only uniqueness** (5.1): `CREATE UNIQUE INDEX … WHERE state NOT IN (<terminal tokens>)` via raw `DB::statement` on BOTH engines (the *structural* guard — NOT driver-guarded, unlike the PG-only value-set CHECK). App pre-check `whereNotIn` MIRRORS the predicate token-for-token; excluded set from the enum (EnumsTest pins it). Test the DB bite with a raw-dup insert in a `DB::transaction` SAVEPOINT (trap 5). Permits terminal-state dups.
- **Spine DB-entity template** (migration + model + factory + event + action), **co-provisioning** (4.1), **preference strings vs cast columns**, **`{@see}`-import trap** (FQN imports, bare/prose don't), **two-FK / optional-FK idiom**, **PII discipline** (event payloads + rejection copy), **date/Money/marker idioms**, **PG17 gate every DB task** — all hold. `lang/en/parties.php` = shared rejection copy (`club`/`producer_agreement`/`customer`/`profile`). **log.md via `memlog.sh` only; hot.md ≤550 words; APPROVED = human-only.**
