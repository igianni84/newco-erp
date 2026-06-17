---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-17
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-17 (ralph — `parties-compliance` task 1.2 green).** Shipped the one additive migration `2026_06_17_000001_add_compliance_to_parties.php`: 8 nullable cols on `parties_customers` (`kyc_status`, `kyc_required`, `enhanced_kyc_flag`, `enhanced_kyc_at`, `sanctions_status`, `last_screening_at`, `next_rescreen_at`, `screening_trigger_source`) + 1 on `parties_producers` (`kyc_status`). All nullable / no-default / no-backfill (DEC-071). PG-only `CHECK (col IS NULL OR col IN (Enum::cases()))` on the 3 Customer value-set cols + Producer `kyc_status` (4 CHECKs via a private `addNullableValueSetCheck` helper). Added casts (3 enum + 2 bool + 3 immutable_datetime) + `@property` to Customer; `kyc_status` cast + `@property` to Producer. Models stay persistence-only (`$guarded = []` untouched).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 629/629 SQLite** (621 baseline + 8 schema tests). PHPStan max 0 · Pint clean · `openspec validate parties-compliance --strict` ✅ · `git diff main -- composer.json composer.lock` empty. **PG17: 132/132 Parties** on `postgres:17` + direct psql CHECK/NULL probes + `down()` reversibility (`migrate:rollback --step=1` → `migrate`). Task 1.2 committed on `ralph/parties-compliance`.

## Active Change & Next Task
- **ACTIVE + APPROVED: `parties-compliance`** (11 tasks, 2/11 done). Branch `ralph/parties-compliance`.
- **Next (1.3):** transition-guard exceptions + localized copy (design L2; no DB). `App\Modules\Parties\Exceptions\IllegalKycTransition` (factories `::cannotRequire/cannotVerify/cannotReject/cannotWaive(KycStatus $from)`) + `IllegalSanctionsTransition` (`::onboardingAlreadyScreened()`, `::cannotResolve(SanctionsStatus $from)`), each `extends RuntimeException`, resolving NEW `kyc` + `sanctions` groups in `lang/en/parties.php` (dotted keys, `:state` placeholder — not PII). **Mirror the existing `IllegalProducerTransition` house style; PRESERVE the `producer`/`club`/`producer_agreement`/`customer`/`profile` lang groups.** Test: `tests/Unit/Modules/Parties/Exceptions/ComplianceTransitionExceptionsTest.php`.

## Blockers & Decisions Needed
- **None.** No open ADR gate crossed (events inline; screening manual-first; no KYC-doc storage). Slice boundary (Hold → `parties-holds`) ratified; cleared-state semantics fixed by ADR 2026-06-17.

## Open Patterns
- **Nullable-value-set migration idiom (1.2):** `string()->nullable()` (no default) + enum cast + PG-only `CHECK (col IS NULL OR col IN (...))` via private `addNullableValueSetCheck`; `down()` drops CHECKs (`IF EXISTS`, pgsql) then cols. Pint strips `\BackedEnum`→`BackedEnum` in the no-namespace migration (PHPStan-clean).
- **timestampTz → `immutable_datetime` cast; in tests assign `CarbonImmutable::now()`, never `now()`** (mutable Carbon trips PHPStan vs a `CarbonImmutable` @property). Nullable bool/enum casts: null stays null.
- **PG-CHECK test = inline savepoint, per-file** — don't reuse the cross-file `captureConstraintViolation` global (a filtered `pest <path>` run won't load it); `try { DB::transaction(...) } catch (QueryException)`, branch on `getDriverName()`. Migration `down()` proven via `migrate:rollback --step=1`, not an in-suite call (`require`→mixed; base `Migration` declares no up/down).
- **PG17 gate** each DB task: `docker run -d --name pg … postgres:17`; `DB_CONNECTION=pgsql … -p 55432`; `docker rm -f pg`. Full suite = `php -d memory_limit=512M vendor/bin/pest`; filtered = file path.
