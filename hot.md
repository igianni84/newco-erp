---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter — task 2.2 `lang/` scaffolding ✅ green; §2 i18n 2 of 4, 7 of 14 tasks done).** Scaffolded the app `lang/` tree for all six locales as **PHP-array group files** (`lang/{locale}/welcome.php`, dotted keys `__('welcome.*')`) — chosen over JSON after verifying vendor (framework ships `lang/{locale}/{auth,validation}.php`; `Translator::get()`→`localeArray()` = `array_filter([$locale,$this->fallback])` gives **native per-key fallback** to `en`). `lang/en/welcome.php` is the complete authored baseline (`headline`/`tagline`/`coming_soon` — the keys 2.4 renders); the other five carry translated `tagline` only and fall back to `en` per key for `headline`/`coming_soon` (partial coverage, AC-0-XM-4). Documented in **`docs/i18n.md`** (+ INDEX row). 9 files (6 lang + doc + test + INDEX edit).

## Build & Quality Status
- Stack: PHP 8.5.2 runtime · Laravel 13.x (^13.8) · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump staged in `substrate-hardening`.)
- Branch `ralph/foundations-money-i18n-flags`: suite **200/200** green (was 196, +4) · phpstan **0** @ max · pint clean · `openspec validate --strict` valid. `git diff main -- composer.json composer.lock` **empty** (Pennant lands only in task 3.1).

## Active Change & Next Task
- **In progress: `foundations-money-i18n-flags` — 7 of 14 tasks done.** §1 Money VOs **5/5**; §2 i18n **2/4** (2.1 registry + 2.2 lang/ done).
- **NEXT = task 2.3 `TranslatableText` VO + `TranslatableTextCast`** (design D4, DEC-064). Per-row i18n-keyed-JSON primitive — a DIFFERENT mechanism from `lang/` (static UI chrome): holds `{locale: text}`; `resolve(?string $locale)` returns the locale's text or the English value **for that attribute only** when absent (per-attribute fallback, DEC-127 item 4 — NOT whole-object). Validate locale keys via `SupportedLocale::assertSupported()` at the app layer (column stays schema-less JSON). Round-trips to/from JSON without loss. Reuse *Immutable composite VO* + *Two-column Eloquent cast* + *Throwaway-table Feature test* patterns — but this cast is **single JSON column** (not two): **verify the Laravel 13 `CastsAttributes` JSON `get`/`set` contract in vendor first** (likely `json_decode`/`json_encode`). Tests: `tests/Unit/Platform/I18n/TranslatableTextTest.php` (VO) + `tests/Feature/Platform/I18n/TranslatableTextCastTest.php` (`RefreshDatabase`, throwaway JSON column). Do NOT attach to any module column (no module entities yet).
- Then 2.4 welcome.blade minimal localized placeholder (renders the `welcome.*` keys just authored) → §3 Pennant (3.1 = ONLY composer-touching task → 3.2 EXT-1 flag) → §4 ActorContext seam → §5 docs/sweep.

## Blockers & Decisions Needed
- None active. Founder-approved default calls stand (DualCurrencyAmount included; welcome.blade → minimal localized placeholder; carry iii = ActorContext seam only).
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). This change touches none.
- **Staged sibling:** `substrate-hardening` (audit fixes incl. `php ^8.3`→`^8.5`) — keep composer churn out of THIS change except Pennant (3.1).

## Open Patterns
- **Read `progress.md` Codebase Patterns first.** Now TEN: *Fixed-set enum VO*, *Fail-closed VO factory*, *Immutable composite VO*, *Structural-invariant reflection test* (tests/ IS under phpstan-max — narrow via `assert`), *String-validated scalar VO*, *Policy-free composite VO + purity guard*, *Two-column Eloquent cast*, *Throwaway-table Feature test*, *Enum-as-SSOT config derives*, plus new **`lang/` PHP-array group files + native per-key fallback** (2.2): `lang/{locale}/{group}.php` + dotted keys (NOT JSON); fallback chain `[locale, fallback]` is native; author `en` complete, others may stagger; test = filesystem assertion off `SupportedLocale::values()` + `Lang::has(key, locale, false)` non-vacuity guard + `assert(is_array(trans('group')))` before `array_keys`. No `declare(strict_types)`, no `final` in `App\Platform` (house style); `config/` NOT in `phpstan.neon` paths → pin config refs with a Feature test.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotation by size (~200KB); `memory-health.sh` warns at Stop. Closing ritual includes a LOCAL PostgreSQL verify (SQLite-green ≠ done).
