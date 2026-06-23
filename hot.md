---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` task 1.1 DONE — `ClubCreditState` enum landed; ralph loop iteration 1/20, 1/15 tasks).** Module K §11 Club Credit, greenfield, extends the `party-registry` capability. Shipped the `string`-backed FSM enum `active → redeemed | forfeited` + its unit test; no DB/Action code yet. The artifacts ARE the plan.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: full suite 1500/1500 (8275 assn); PHPStan max 0 err; `pint --test` clean.**
- **Run the full suite with `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`.** The `artisan test` wrapper (laravel/pao) spawns the real test process *without* the outer `-d memory_limit=-1`, so it OOMs at the 128 MB default (fatal in `filament/.../routes/web.php` during `setUp()`). `vendor/bin/pest` keeps it one unlimited process.

## Active Change & Next Task
- **`club-credit` — 1/15 done.** Next: **1.2 migration** `<ts>_create_parties_club_credits_table.php` — table `parties_club_credits` (`profile_id` FK `restrictOnDelete()`; MoneyCast cols `amount_minor/_currency` + `remaining_minor/_currency`; `valid_from`/`valid_to`; `state` string; timestamps) + the **partial unique index** `parties_club_credits_one_active_per_profile (profile_id) WHERE state='active'` via raw `DB::statement` on **both** engines (precedent: `2026_06_15_000007_create_parties_profiles_table.php`, not driver-guarded). **First DB-touching task → verify on PostgreSQL 17 too.** Then 1.3 model+factory → 1.4 `Profile::activeClubCredit()` → 2.x issuance → 3.x redemption (K.17) → 4.x forfeit/restore → 5.x §11.4 guard + i18n + docs + gate.
- 3 gate decisions RESOLVED (design L2/L3/L5): **audit-only writers** (no domain event — §11.4 makes `ClubCredit*` + `MembershipFeePaid` Module E's); **`Club.fee` verbatim** (full-fee→full-credit, `valid_to`=31 Dec); **full FSM + seams** (shipped Profile Actions untouched).

## Blockers & Decisions Needed
- **No active blocker.** No open ADR gate stepped through (audit-only writers add no queued consumer / object-storage / payment-provider / frontend). `main` in sync with `origin/main`.
- Cross-module triggers are deferred SEAMS (not blockers): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-043 closure conversion; year-end scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **Parties state-enum idiom** (`KycStatus`/`HoldType` templates): `string` enum, no `declare(strict_types=1)`, PascalCase cases / lowercase values, domain rules as one-line predicate methods used as readable from-state guards; tested in `tests/Unit/Modules/Parties/Enums/{Domain}EnumsTest.php` (verbatim ordered `toBe([...])` map + `from()` round-trip + predicate truth tables + `ValueError` on bogus). `isActive()` feeds Apply/Forfeit guards (3.1/4.1).
- **Audit-only writer for Module-E-owned events:** §11.4 writers record state + emit NO event (mirrors `RecordKycVerified`); 5.1 guard test asserts no `ClubCredit*`/`MembershipFeePaid` class under Parties + zero `domain_events` rows.
- **Recalled-memory staleness:** verify a memory claim (e.g. push-gate flags) via `git rev-list` before acting.
