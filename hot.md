---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter — task 1.5 `MoneyCast` ✅ green; Section 1 Money VOs 5/5 COMPLETE).** Implemented `App\Platform\Money\MoneyCast` — the reusable two-column Eloquent `CastsAttributes` (the on-disk counterpart of `Money::of()`; money discipline invariant 6 with no float column/path). **Convention:** columns derived from the cast key — `'price' => MoneyCast::class` ⇒ `price_minor` (int) + `price_currency` (ISO string), the `{key}_minor`/`{key}_currency` naming F2 follows. `set(Money|null)` returns the two-column array (Laravel spreads it via `array_replace`); `get()` reads the two raw cols from `$attributes` → `Money::of((int)$minor, Currency::of($cur))`. Generic `@implements CastsAttributes<Money, mixed>` (TGet=Money types the model prop; TSet=mixed keeps the runtime `instanceof` guard load-bearing). Fail-closed both ways (non-Money `set()` + corrupt `get()` column throw). Null money ↔ null columns. 2 files (impl + FIRST DB-touching test, 6/6).

## Build & Quality Status
- Stack: PHP 8.5.2 runtime · Laravel 13.x (^13.8) · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump staged in `substrate-hardening`.)
- Branch `ralph/foundations-money-i18n-flags`: suite **183/183** green (was 177, +6) · phpstan **0** @ max · pint clean · `openspec validate --strict` valid. `git diff main -- composer.json composer.lock` **empty** (Pennant lands only in task 3.1).

## Active Change & Next Task
- **In progress: `foundations-money-i18n-flags` (F1 3/3) — 5 of 14 tasks done.** Section 1 (Money VOs) is **5/5 COMPLETE** (Currency · Money · FxRate · DualCurrencyAmount · MoneyCast).
- **NEXT = task 2.1 `SupportedLocale`** — starts §2 i18n. `config/i18n.php` + a `SupportedLocale` **enum** (the typed anchor) listing exactly `en, it, fr, de, ja, zh_Hans` (Laravel underscore form; spec AC-0-XM-4 "JA"/"zh-Hans"), `en` as fallback, an `isSupported`/`assertSupported` helper. Pure enum, NO DB → `tests/Unit/Platform/I18n/SupportedLocaleTest.php`; mirror the *Fixed-set enum VO* + *Verbatim set pin* patterns (EnumsTest style — loop `$values[$case->name]=$case->value` then order-sensitive `->toBe([...])`); `isSupported('es')`/`'zh_Hant'` false, all six true, fallback accessor → `en`. `config/app.php` `locale`/`fallback_locale` already `en` — leave as-is.
- Then §2: 2.2 `lang/` scaffolding (verify Laravel 13 `lang/{locale}/*.php` vs `lang/{locale}.json` in vendor; document choice) → 2.3 `TranslatableText` VO + `TranslatableTextCast` (reuse the two NEW cast patterns) → 2.4 welcome.blade minimal localized placeholder. Then §3 Pennant (3.1 install = ONLY composer-touching task → 3.2 EXT-1 flag) → §4 ActorContext seam → §5 docs/sweep.

## Blockers & Decisions Needed
- None active. Founder-approved default calls stand (DualCurrencyAmount included; welcome.blade → minimal localized placeholder; carry iii = ActorContext seam only).
- **Open ADR gates (do not step into):** identity/auth (before Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). This change touches none.
- **Staged sibling:** `substrate-hardening` (audit fixes incl. `php ^8.3`→`^8.5`) — keep composer churn out of THIS change except Pennant (3.1).

## Open Patterns
- **Read `progress.md` Codebase Patterns first.** Now SEVEN patterns: *Fixed-set enum VO* (Currency — reuse for SupportedLocale 2.1), *Immutable composite VO*, *Structural-invariant reflection test* (tests/ IS under phpstan-max — narrow `ReflectionType|null` / `?Vo` via `assert`), *String-validated scalar VO* (FxRate), *Policy-free composite VO + purity guard* (DualCurrencyAmount), and TWO new: *Two-column Eloquent cast* (MoneyCast — key-derived cols; array-return multi-col write; `<TVo, mixed>` generic; narrow `mixed` `$attributes` with `is_numeric`/`is_string`, NOT `(int)`-cast — `cast.int` is a phpstan-max error) + *Throwaway-table Feature test* (`Schema::dropIfExists`+`create` in `beforeEach` under `RefreshDatabase`; named fixture w/ `@property int $id`; `findOrFail($model->id)` w/ an **int** key avoids the `Collection|Model` union → `property.notFound`; `toEqual` for raw int cols — SQLite-int vs PG-numeric-string). No `declare(strict_types)`, no `final` in `App\Platform` (house style).
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotation by size (~200KB); `memory-health.sh` warns at Stop. Closing ritual includes a LOCAL PostgreSQL verify (SQLite-green ≠ done).
- **Substrate (`App\Platform`)**: boundary law arch-test-enforced (prefix-matched — sub-namespaces free); recorder consumes payload arrays verbatim (never rehydrates Money); envelope UUIDv7 + minor-units + FX decimal-string; module identities cross as `string`.
