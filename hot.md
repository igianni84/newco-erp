---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter — task 1.3 `FxRate` ✅ green).** Implemented `App\Platform\Money\FxRate`: immutable VO wrapping ONE exact decimal string (typed enforcement of "FX rates = decimal strings, never floats"). Private ctor + single `public readonly string $value`; `of(string)` sole path (no float overload), validates against class-const `DECIMAL_PATTERN = '/\A\d+(\.\d+)?\z/'` (`preg_match !== 1` → SPL `InvalidArgumentException`); accepted string stored **verbatim** (no normalisation = bit-for-bit, invariant 5 refund rate). `__toString()` returns value. No `equals`/`toPayload` (YAGNI; 1.4 builds DEC-169 payload itself). 2 files (FxRate.php + FxRateTest 5/5). **No composer churn.**

## Build & Quality Status
- Stack: PHP 8.5.2 runtime · Laravel 13.x (^13.8) · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump staged in `substrate-hardening`.)
- Branch `ralph/foundations-money-i18n-flags`: suite **171/171** green (was 166, +5) · phpstan **0** @ max · pint clean · `openspec validate --strict` valid. `git diff main -- composer.json composer.lock` empty (Pennant lands only in task 3.1).

## Active Change & Next Task
- **In progress: `foundations-money-i18n-flags` (F1 3/3) — 3 of 14 tasks done.**
- **NEXT = task 1.4 `DualCurrencyAmount`** (`tests/Unit/Platform/Money/DualCurrencyAmountTest.php`): VO bundling customer `Money` + EUR `Money` + locked `FxRate` + rate date → DEC-169 payload `amount`/`currency`/`eur_equivalent_amount`/`fx_rate`/`fx_rate_date`. Assert the EUR leg `=== Currency::EUR` at construction (mirror Money's `assertSameCurrency` private-guard idiom → SPL `InvalidArgumentException`). Build `fx_rate` from `$fx->value` (NOT a Money payload — different keys). **Pure representation: carry NO FX policy** — 1.4 guard test asserts no rate-deriving method via `get_class_methods` allow-list. Pass the rate date IN (`CarbonImmutable` literal / `Date::setTestNow`), never `now()` in an assertion. 3 delta scenarios + the purity guard.
- Then 1.5 MoneyCast (Feature + RefreshDatabase) → §2 i18n (2.1 SupportedLocale enum → 2.2 lang/ → 2.3 TranslatableText → 2.4 welcome.blade) → §3 Pennant (3.1 install → 3.2 EXT-1) → §4 ActorContext seam → §5 docs/sweep.

## Blockers & Decisions Needed
- None active. Founder-approved default calls stand (DualCurrencyAmount included now; welcome.blade → minimal localized placeholder; carry iii = ActorContext seam only).
- **Open ADR gates (do not step into):** identity/auth (before Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). This change touches none.
- **Staged sibling:** `substrate-hardening` (audit fixes incl. `php ^8.3`→`^8.5`) — keep composer churn out of THIS change except Pennant (3.1).

## Open Patterns
- **Read `progress.md` Codebase Patterns first.** Now four VO patterns: *Immutable composite VO* (Money — private ctor + promoted `public readonly` props + sole `of()` factory; derivations return new instances; private `assert*` guards → SPL exception), *Fixed-set enum VO* (Currency/SupportedLocale), *Structural-invariant reflection test* (no-float proof; narrow `ReflectionType|null`; `tests/` IS under phpstan-max), and new *String-validated scalar VO* (FxRate — `\A…\z` anchoring NOT `^…$`; `preg_match !== 1`; store **verbatim**, well-formedness only, economic validity is downstream policy). No `declare(strict_types)`, no `final` anywhere in `App\Platform` (house style).
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotation by size (~200KB); `memory-health.sh` warns at Stop. Closing ritual includes a LOCAL PostgreSQL verify (SQLite-green ≠ done).
- **Substrate (`App\Platform`)**: boundary law arch-test-enforced (prefix-matched — sub-namespaces free); recorder consumes payload arrays verbatim (never rehydrates Money); envelope UUIDv7 + minor-units + FX decimal-string; module identities cross as `string`.
