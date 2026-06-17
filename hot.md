---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-17
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-17 (ralph — `parties-compliance` task 1.3 green).** Shipped the two compliance transition-guard exceptions + localized copy (no DB). `App\Modules\Parties\Exceptions\IllegalKycTransition` (`::cannotRequire/cannotVerify/cannotReject/cannotWaive(KycStatus $from)` — shared by Customer 2.1 + Producer 3.1 KYC) and `IllegalSanctionsTransition` (`::onboardingAlreadyScreened()` — no `:state`, prior-screening timestamp is PII; `::cannotResolve(SanctionsStatus $from)`). Both `extends RuntimeException`, mirroring `IllegalProducerTransition`: `new self((string) __('parties.<group>.<key>', ['state' => $from->value]))`. Added new `kyc` + `sanctions` groups to `lang/en/parties.php` (6 keys, `:state` placeholder, PII-free); the 5 pre-existing groups untouched.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 642/642 SQLite** (629 baseline + 13 new). PHPStan max 0 · Pint clean · `openspec validate parties-compliance --strict` ✅ · `git diff main -- composer.json composer.lock` empty. Task 1.3 committed on `ralph/parties-compliance`. **No DB touched this task → PG17 gate N/A** (pure PHP: exceptions + lang + test). PG17 standing baseline from 1.2: 132/132 Parties on `postgres:17`.

## Active Change & Next Task
- **ACTIVE + APPROVED: `parties-compliance`** (11 tasks, 3/11 done). Branch `ralph/parties-compliance`.
- **Next (2.1):** Customer KYC lifecycle Actions — `RequireKyc` + `RecordKycVerified` + `RecordKycRejected` under `app/Modules/Parties/Actions/` (design L2/L3; spec — Customer KYC Lifecycle). Each `DB::transaction` → `lockForUpdate` re-read → from-state assert → write `kyc_status` (RequireKyc also sets `kyc_required = true`). `RequireKyc`: `NotRequired`/NULL → `Pending` (else `IllegalKycTransition::cannotRequire`); verify/reject: `Pending → Verified`/`Rejected` (else `::cannotVerify`/`::cannotReject`). **No domain event** (L3 — audit-only), **no Hold** (scope guard). Mirror the `ActivateProducer`/`SunsetClub` Action idiom (sole writer; Models stay `$guarded = []`). Test `tests/Feature/Modules/Parties/CustomerKycLifecycleTest.php` (`RefreshDatabase`) — assert `domain_events` count unchanged + Customer `status` (`pending`) untouched. **DB-touching → PG17-verify before close.**

## Blockers & Decisions Needed
- **None.** No open ADR gate crossed (events inline; screening manual-first; no KYC-doc storage). Slice boundary (Hold → `parties-holds`) ratified; cleared-state semantics fixed by ADR 2026-06-17.

## Open Patterns
- **Compliance exception vocabulary is live (1.3).** Downstream Actions call: `cannotRequire/Verify/Reject` (2.1 + 3.1), `cannotWaive` (3.1 only — illegal when already `not_required`), `onboardingAlreadyScreened` + `cannotResolve` (4.2). KYC records **no event** → these exceptions are the only rejected-transition signal besides the unchanged column.
- **Lang-key interpolation test idiom:** `uses(TestCase::class)` (no DB), pick a from-state token absent from each key's template to prove `:state` interpolation; `->with([...])` dataset with neutral probe `'suspended'`; a final case re-asserts the 5 pre-existing groups (regression).
- **Nullable-value-set migration idiom (1.2):** `string()->nullable()` (no default) + enum cast + PG-only `CHECK (col IS NULL OR col IN (...))` via private `addNullableValueSetCheck`; `down()` drops CHECKs then cols. timestampTz → `immutable_datetime` cast; in tests assign `CarbonImmutable::now()`, never `now()`.
- **PG17 gate** each DB task: `docker run -d --name pg … postgres:17`; `DB_CONNECTION=pgsql … -p 55432`; `docker rm -f pg`. Full suite = `php -d memory_limit=512M vendor/bin/pest`; filtered = file path.
