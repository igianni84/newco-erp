# Progress — parties-enhanced-kyc-threshold

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Run the FULL suite as `php -d memory_limit=2G vendor/bin/pest`.** `php artisan test` re-spawns Pest as a child process that does NOT inherit an outer `-d memory_limit` flag, and the ~1889-test suite exhausts the 128M CLI default during result collection (a `TestResult.php` fatal, not a test failure). Filtered runs (`php artisan test --filter=Name`) fit in 128M fine.
- **Read DB scalars with `->value('col')`, not `->first()->prop`.** PHPStan (max) flags `DB::table(...)->first()->reason` as property access on `stdClass|null`. `->value('col')` returns the scalar directly (the `ClubCreditSchemaTest` idiom) — no nullable-object access, no suppression needed. Assert money/bigint columns with `->toEqual` (PG returns bigint as a numeric string), never `->toBe`.
- **Value-set CHECK shape follows nullability.** A NOT-NULL enum-backed column → plain PG-only `CHECK (col IN (...))`; an additive-nullable column → `CHECK (col IS NULL OR col IN (...))` (the `2026_06_17_000001_add_compliance_to_parties` variant). Both derive the accepted set from `Enum::cases()` (guarded by `DB::getDriverName() === 'pgsql'`) so the constraint can never drift; on SQLite the enum cast is the floor.

---

## [2026-07-02 19:49] — 1.1 Migration: parties_compliance_reviews
- **Implemented** `database/migrations/2026_07_02_000003_create_parties_compliance_reviews_table.php`: `id`; within-module FK `customer_id`→`parties_customers` (RESTRICT, explicit short index `parties_comp_reviews_customer_fk`); `reason` + `threshold_kind` (string + enum cast + PG-only value-set CHECK derived from `::cases()`); `tripped_amount_minor` (`bigInteger`) + `tripped_currency` (3); nullable `resolved_at` (`timestampTz`); `timestampsTz`. Both value-set columns are NOT NULL → plain `IN (...)` CHECK. Dev-only `down()`. Postgres-truthful + SQLite-compatible, additive, no PG extension.
- **Prerequisite created** — the two enums `ComplianceReviewReason` (`EnhancedKycThreshold`, sole case) + `ThresholdKind` (`SingleTransaction`/`CumulativeAnnual`). Task 1.1's CHECK derives from `::cases()`, so the migration cannot compile without them; hard-coding the tokens would violate the mandated idiom and force a later migration edit. **⇒ Task 2.1's enums ALREADY EXIST** — 2.1's remaining work is ONLY its dedicated enum unit test (case→value mapping + `count(ComplianceReviewReason::cases())===1`).
- **Test** `tests/Feature/Modules/Parties/ComplianceReviewSchemaTest.php` (6 tests, 12 assertions): `hasColumns` (9 cols); open-row insert + amount round-trip via `->value()`/`->toEqual`; `customer_id` column-type == `parties_customers.id`; FK orphan rejection; PG-CHECK-rejects-vs-SQLite-accepts asymmetry for `reason` AND `threshold_kind` (savepoint-wrapped).
- **Files changed:** `ComplianceReviewReason.php`, `ThresholdKind.php`, the migration, the schema test, `tasks.md` (1.1 flipped).
- **Quality loop: green** — Pint clean; filtered 6/6; full suite **1889/1889** (SQLite `:memory:`, via `php -d memory_limit=2G vendor/bin/pest`); PHPStan **0**; `openspec validate --strict` valid. PG17 CHECK-rejection branch runs in the CI tests-pgsql lane / task 7.1 cross-engine close (no local PG server).
- **Learnings for future iterations:**
  - See the three Codebase Patterns above (suite memory-limit command; `->value()` over `->first()->prop`; NOT-NULL vs nullable CHECK shape) — all discovered this iteration.
  - Migration index sequence is continuous: this is `2026_07_02_000003` (after `...000002_create_parties_addresses_table`).
  - The FK index name is `parties_comp_reviews_customer_fk` (abbreviated — the long `parties_compliance_reviews` table name keeps auto-names near PG's 63-char limit). Auto-named CHECKs `parties_compliance_reviews_{reason,threshold_kind}_check` are 39/47 chars — safe.
---
