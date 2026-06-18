# Progress — parties-membership-activation

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Additive-nullable-timestamp migration (no CHECK).** Plain `timestampTz()->nullable()` columns need NO PG-only value-set CHECK — there is no enum to pin (the type is the domain); the `immutable_datetime` cast carries the typed-read floor on both engines. Simpler than `2026_06_17_000001` (which adds CHECKs because its columns are enum-backed). `down()` is a bare `dropColumn([...])` — nothing to drop first.
- **Isolated PG17 reversibility proof.** To prove ONE migration's `down()`/`up()` on PG without rolling back the whole chain: `migrate:fresh` → `migrate:rollback --path=<migration>` (runs only that down()) → assert columns gone + table intact (`information_schema.columns` count + `to_regclass(...) IS NOT NULL`) → `migrate --path=<migration>` (re-up) → assert columns back. (`--path` rollback prints a cosmetic "0001_..._create_users_table Migration not found" line — the absent default users migration — ignore it; the column count delta is the truth.)
- **Null-safe round-trip assertion for nullable datetime casts.** Assert `expect($read->col)->toBeInstanceOf(CarbonImmutable::class)` (proves non-null) AND `->and($read->col?->format('Y-m-d H:i:s'))->toBe('...')` (value round-trip). The `?->` keeps PHPStan-max happy on a `CarbonImmutable|null` property without `assert()`/`@var`/casts; the `->format()` read-through is testing-rule #4 (never byte-compare a raw `timestamptz` — it renders `+00` on PG). Use fixed no-microsecond moments so the round-trip is deterministic across engines.
- **The arch OOM hits the full suite on SQLite too, not just PG.** `php artisan test` (full) fataled at the default 128 MB `memory_limit` on SQLite (pest-plugin-arch docblock parsing). Run the FULL suite on either engine with `php -d memory_limit=512M vendor/bin/pest`. (A single filtered file runs fine at default memory.)

---

## [2026-06-18 20:49] — 1.1 Additive migration — onboarding-acceptance timestamps on `parties_customers`
- **Implemented:** the additive migration adding three nullable `timestampTz` columns (`email_verified_at`, `tc_accepted_at`, `privacy_accepted_at`) to `parties_customers` — the `ActivateCustomer` composite-gate inputs (§ 4.1; DEC-071/DEC-073). No CHECK (plain timestamps), reversible `down()`, no change to `parties_profiles`. Added `immutable_datetime` casts + `@property ?CarbonImmutable` for each on `Customer` + a docblock paragraph (born NULL, deferred registration-surface/operator writer, PII-free).
- **Files changed:** `database/migrations/2026_06_18_000002_add_onboarding_acceptance_to_parties_customers.php` (new); `app/Modules/Parties/Models/Customer.php` (casts + 3 `@property` + docblock); `tests/Feature/Modules/Parties/CustomerOnboardingAcceptanceTest.php` (new — 3 tests: born-NULL, typed round-trip, columns-exist-incl-PG17).
- **Quality loop:** green. format · test_filter (3/3) · full suite **786/786** (was 783; +3) · phpstan 0 · lint — all green on SQLite. **Verified on PG17** (docker `postgres:17`): `migrate:fresh` applies the migration (3 cols = `timestamp with time zone`, nullable, no default); full suite **786/786** on PG17; isolated `down()`/`up()` reversibility proven (3 → 0 cols with table intact → 3). `openspec validate --strict` green. `git diff main -- composer.json composer.lock` empty.
- **Learnings for future iterations:**
  - The four Codebase Patterns above (additive-timestamp migration shape, isolated PG17 reversibility, null-safe datetime round-trip, the SQLite arch-OOM 512M flag).
  - One PHPStan-max gotcha hit & fixed in-task: chaining `->eq()` directly on a nullable cast property fails `method.nonObject`; the `?->format(...)` idiom is the clean fix (no suppression).
  - Next task **1.2** (the two `Illegal*Transition` exceptions + localized copy) is pure PHP/i18n — no DB, so no PG run needed (its acceptance says so). The columns this task shipped are read by **2.3** `ActivateCustomer`.
---
