---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter — task 1.4 `DualCurrencyAmount` ✅ green).** Implemented `App\Platform\Money\DualCurrencyAmount`: the D18 dual-record bundle — customer `Money` (`amount`) + EUR-equiv `Money` (`eurEquivalent`) + locked `FxRate` (`fxRate`) + `DateTimeImmutable` rate timestamp (`fxRateDate`), one immutable VO. Private ctor + promoted `public readonly` props; sole `of(Money,Money,FxRate,DateTimeImmutable)`. `toPayload()` → exact DEC-169 shape `amount`/`currency`/`eur_equivalent_amount`/`fx_rate`/`fx_rate_date` (spec DEC-169 line 2004), hand-built from the legs (NOT `Money::toPayload()` — different keys). EUR leg asserted `=== Currency::base()` via private `assertEurLeg()` in the ctor body. `fx_rate_date` = full ISO-8601 (`DateTimeImmutable::ATOM`), NOT truncated to `Y-m-d` (truncation = snapshot-policy = D3 landmine). 2 files (impl + test 6/6).

## Build & Quality Status
- Stack: PHP 8.5.2 runtime · Laravel 13.x (^13.8) · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump staged in `substrate-hardening`.)
- Branch `ralph/foundations-money-i18n-flags`: suite **177/177** green (was 171, +6) · phpstan **0** @ max · pint clean · `openspec validate --strict` valid. `git diff main -- composer.json composer.lock` empty (Pennant lands only in task 3.1).

## Active Change & Next Task
- **In progress: `foundations-money-i18n-flags` (F1 3/3) — 4 of 14 tasks done.** Section 1 (Money VOs) is 4/5 — only the cast remains.
- **NEXT = task 1.5 `MoneyCast`** — the FIRST DB-touching task. `tests/Feature/Platform/Money/MoneyCastTest.php` with `uses(RefreshDatabase::class)`; throwaway migration+model with `amount_minor` (int) + `amount_currency` (string) columns. `set()` returns `['amount_minor'=>$m->minorUnits,'amount_currency'=>$m->currency->value]`; `get()` rebuilds via `Money::of((int)$attributes['amount_minor'], Currency::of($attributes['amount_currency']))`. **Verify the Laravel 13 `CastsAttributes` two-column `get()`/`set()` contract in `vendor/` first** (multi-column return convention is easy to mis-remember — lessons.md). Assert write→reload equals + raw columns (`1999`,`'EUR'`).
- Then §2 i18n (2.1 SupportedLocale enum → 2.2 lang/ → 2.3 TranslatableText → 2.4 welcome.blade) → §3 Pennant (3.1 install → 3.2 EXT-1) → §4 ActorContext seam → §5 docs/sweep.

## Blockers & Decisions Needed
- None active. Founder-approved default calls stand (DualCurrencyAmount included now; welcome.blade → minimal localized placeholder; carry iii = ActorContext seam only).
- **Open ADR gates (do not step into):** identity/auth (before Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). This change touches none.
- **Staged sibling:** `substrate-hardening` (audit fixes incl. `php ^8.3`→`^8.5`) — keep composer churn out of THIS change except Pennant (3.1).

## Open Patterns
- **Read `progress.md` Codebase Patterns first.** Five VO patterns now: *Immutable composite VO* (Money — private ctor + promoted `public readonly` + sole `of()`; derivations return new instances; private `assert*` guards → SPL exception), *Fixed-set enum VO* (Currency/SupportedLocale), *Structural-invariant reflection test* (no-float / immutable-date proof; narrow `ReflectionType|null`; `tests/` IS under phpstan-max), *String-validated scalar VO* (FxRate — `\A…\z` anchoring; verbatim storage), and new *Policy-free composite VO + purity guard* (DualCurrencyAmount — hand-built payload vs composed `toPayload`; `DateTimeImmutable` leg + full-ISO-8601 no-truncation; `get_class_methods` sorted purity allow-list; ctor-body `assert*` precondition). No `declare(strict_types)`, no `final` in `App\Platform` (house style).
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotation by size (~200KB); `memory-health.sh` warns at Stop. Closing ritual includes a LOCAL PostgreSQL verify (SQLite-green ≠ done).
- **Substrate (`App\Platform`)**: boundary law arch-test-enforced (prefix-matched — sub-namespaces free); recorder consumes payload arrays verbatim (never rehydrates Money); envelope UUIDv7 + minor-units + FX decimal-string; module identities cross as `string`.
