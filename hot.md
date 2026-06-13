---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter — task 1.2 `Money` ✅ green).** Implemented `App\Platform\Money\Money`: immutable VO of `int $minorUnits` + `Currency` (composes the 1.1 enum). Private ctor + promoted `public readonly` props; `of(int, Currency)` sole construction path (no float overload). `plus`/`minus`/`negate` return new instances on minor units; same-currency guard (`assertSameCurrency` → SPL `InvalidArgumentException`); `equals()` value equality; `toPayload()` → `['minor_units'=>int,'currency'=>string]`. Negatives valid. 2 files (Money.php + MoneyTest 8/8). **No composer churn.**

## Build & Quality Status
- Stack: PHP 8.5.2 runtime · Laravel 13.x (^13.8) · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump staged in `substrate-hardening`.)
- Branch `ralph/foundations-money-i18n-flags`: suite **166/166** green (was 158, +8) · phpstan **0** @ max · pint clean · `openspec validate --strict` valid. `git diff main -- composer.json composer.lock` empty (Pennant lands only in task 3.1).

## Active Change & Next Task
- **In progress: `foundations-money-i18n-flags` (F1 3/3) — 2 of 14 tasks done.**
- **NEXT = task 1.3 `FxRate`** (`tests/Unit/Platform/Money/FxRateTest.php`): VO wrapping a validated EXACT decimal string; **no float path**; round-trips the string verbatim; rejects malformed strings. 3 delta scenarios — `(string) FxRate::of('1.0842')` → `'1.0842'` (`->toBeString()`); `'1.08.42'`/`'abc'`/`''` each throw; reflection assertion the `of()` param is `string` not `float` (mirror the 1.2 reflection idiom). Follow the "Immutable composite VO" + "Fail-closed VO factory" patterns.
- Then 1.4 DualCurrencyAmount (two `Money` legs + `FxRate` + date → DEC-169 shape `amount`/`currency`/`eur_equivalent_amount`/`fx_rate`/`fx_rate_date`; pure representation, no FX policy) → 1.5 MoneyCast (Feature + RefreshDatabase) → §2 i18n → §3 Pennant → §4 ActorContext → §5 docs/sweep.

## Blockers & Decisions Needed
- None active. Founder-approved default calls stand (DualCurrencyAmount included now; welcome.blade → minimal localized placeholder; carry iii = ActorContext seam only).
- **Open ADR gates (do not step into):** identity/auth (before Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). This change touches none.
- **Staged sibling:** `substrate-hardening` (audit fixes incl. `php ^8.3`→`^8.5`) — keep composer churn out of THIS change except Pennant (3.1).

## Open Patterns
- **Read `progress.md` Codebase Patterns first.** New this iter: **Immutable composite VO** (private ctor + promoted `public readonly` props + sole `of()` factory; derivations return new instances; `equals()` value-equality; `toPayload()` with `@return array{…}` shape; rehydrate by composing public factories, NOT a dead `fromPayload`; private `assert*` guards → SPL exception; no `final`, no `declare(strict_types)`) — the template for FxRate 1.3 + DualCurrencyAmount 1.4. **Structural-invariant reflection test** (no-float proof): `ReflectionMethod('of')->getParameters()[0]->getType()`, narrow with `assert(...instanceof ReflectionNamedType)` for PHPStan-max (**`tests/` IS under phpstan**), assert `getName()` is `int` + ctor `isPrivate()`.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotation by size (~200KB); `memory-health.sh` warns at Stop. Closing ritual includes a LOCAL PostgreSQL verify (SQLite-green ≠ done).
- **Substrate (`App\Platform`)**: boundary law arch-test-enforced (prefix-matched — sub-namespaces free); recorder consumes payload arrays verbatim (never rehydrates Money); envelope UUIDv7 + minor-units + FX decimal-string; module identities cross as `string`.
