---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter 1/20 — `foundations-money-i18n-flags` task 1.1 DONE).** Implemented `App\Platform\Money\Currency`: a `string`-backed enum, launch ISO 4217 set fixed at five (EUR base/USD/GBP/CHF/JPY, DEC-037), `minorUnitExponent()` via exhaustive `match` (JPY 0, cents 2), `base()`→EUR, fail-closed `of(string)` factory (`tryFrom ?? throw InvalidArgumentException`, exact match, no case-folding). 7 new tests. Suite 151→**158/158**. No dep churn (owned code).

## Build & Quality Status
- Stack: PHP 8.5.2 runtime · Laravel 13.x (^13.8) · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump staged in substrate-hardening.)
- Branch `ralph/foundations-money-i18n-flags`: suite **158/158** green · phpstan **0** @ max · pint clean · `openspec validate --strict` green. composer.json/.lock untouched so far (Pennant added only in task 3.1).

## Active Change & Next Task
- **In progress: `foundations-money-i18n-flags` (F1 3/3) — 1 of 14 tasks done.**
- **NEXT = task 1.2 `Money`** (`tests/Unit/Platform/Money/MoneyTest.php`): immutable VO of `int $minorUnits` + `Currency`; **no float construction path** (assert via reflection the factory param type is `int`); `plus`/`minus`/`negate` with same-currency guard; value equality; negatives valid; `toPayload()`→`['minor_units'=>int,'currency'=>'EUR']`. Compose `Currency::of()` to rehydrate, `$currency->value` to serialise the code. 5 delta scenarios.
- Then 1.3 FxRate (decimal-string VO) → 1.4 DualCurrencyAmount (DEC-169 shape, pure representation) → 1.5 MoneyCast (Feature + RefreshDatabase) → §2 i18n → §3 Pennant → §4 ActorContext → §5 docs/sweep.

## Blockers & Decisions Needed
- None active. Founder-approved default calls stand (DualCurrencyAmount included now; welcome.blade → minimal localized placeholder; carry iii = ActorContext seam only).
- **Open ADR gates (do not step into):** identity/auth (before Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). This change touches none.
- **Staged sibling:** `substrate-hardening` (audit fixes incl. `php ^8.3`→`^8.5`) — keep composer churn out of THIS change except Pennant (3.1).

## Open Patterns
- **`progress.md` Codebase Patterns is now populated — read it first.** Reusables for §1: fixed-set VO → string-backed enum + exhaustive `match`; fail-closed `of()` = `tryFrom ?? throw InvalidArgumentException`; no `declare(strict_types=1)` in `app/Platform`; verbatim order-sensitive set pin mirroring `tests/Unit/Platform/EnumsTest.php`; pure VO tests → `tests/Unit/Platform/<Sub>/`, cast tests → `tests/Feature/Platform/` with per-file `uses(RefreshDatabase::class)`.
- **F1 3/3 landmines (design D1–D7):** verify vendor APIs before writing (Pennant define/active + migration SQLite-clean, Laravel 13 custom casts, `lang/` file convention); `DualCurrencyAmount` = pure representation (no FX policy — Module E); `ActorContext` imports no auth/module code; composer churn bounded to Pennant.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotation by size (~200KB); `memory-health.sh` warns at Stop. Closing ritual includes a LOCAL PostgreSQL verify (SQLite-green ≠ done).
- **Substrate (`App\Platform`)**: boundary law arch-test-enforced (prefix-matched — sub-namespaces free); recorder rides caller's transaction; envelope UUIDv7 + minor-units + FX decimal-string; inline post-commit + `events:sweep` at-least-once; immutability via DB triggers (SQLite/PG parity); module identities cross as `string`.
