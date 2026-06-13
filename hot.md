---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter — task 2.1 `SupportedLocale` ✅ green; §2 i18n started, 6 of 14 tasks done).** Implemented `App\Platform\I18n\SupportedLocale` — a `string`-backed enum, the single source of truth ("typed anchor", design D4) for the six launch locales (DEC-031/AC-0-XM-4): `En=en · It=it · Fr=fr · De=de · Ja=ja · ZhHans=zh_Hans` (PascalCase case name per ActorRole; value = the canonical locale string, `zh_Hans` underscore form). `fallback()`→`En` (single accessor, mirrors `Currency::base()`); `values(): list<string>` derives the code list; `isSupported(string):bool` predicate + `assertSupported(string):self` fail-closed factory (`tryFrom ?? throw InvalidArgumentException` naming the set; exact match, no case-folding — mirrors `Currency::of()`). Added `config/i18n.php` deriving `'supported'=>SupportedLocale::values()` + `'fallback'=>...En->value` FROM the enum (one source, can't drift). `config/app.php` locale/fallback already `en` — left as-is. 4 new files (enum + config + Unit pin + Feature config-pin).

## Build & Quality Status
- Stack: PHP 8.5.2 runtime · Laravel 13.x (^13.8) · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump staged in `substrate-hardening`.)
- Branch `ralph/foundations-money-i18n-flags`: suite **196/196** green (was 183, +13) · phpstan **0** @ max · pint clean · `openspec validate --strict` valid. `git diff main -- composer.json composer.lock` **empty** (Pennant lands only in task 3.1).

## Active Change & Next Task
- **In progress: `foundations-money-i18n-flags` — 6 of 14 tasks done.** §1 Money VOs **5/5 COMPLETE**; §2 i18n **1/4** (2.1 done).
- **NEXT = task 2.2 `lang/` scaffolding** (design D4). **Verify the Laravel 13 file convention in `vendor/` FIRST** — `lang/{locale}/*.php` PHP-array files vs `lang/{locale}.json` — pick one and **document the choice in `docs/`** (task requires it; 5.1 references it too). No `lang/` dir exists yet (Laravel 13 ships translations in vendor, publishes via `php artisan lang:publish` — verify before assuming a path). Author English with the keys this change introduces (incl. the welcome-page keys 2.4 needs); create a resolvable resource for ALL SIX locales (the other five may partially cover + fall back to `en` per key). Test `tests/Feature/Platform/I18n/LocalizationTest.php`: drive the per-locale filesystem assertion off `SupportedLocale::values()` (so a missing resource can't silently drift) + `App::setLocale('it')` then `__('<key>')` returns it-value when present, en-value when not.
- Then 2.3 `TranslatableText` VO + cast (reuse the two cast patterns + `SupportedLocale::assertSupported` for key validation) → 2.4 welcome.blade minimal localized placeholder → §3 Pennant (3.1 = ONLY composer-touching task → 3.2 EXT-1 flag) → §4 ActorContext seam → §5 docs/sweep.

## Blockers & Decisions Needed
- None active. Founder-approved default calls stand (DualCurrencyAmount included; welcome.blade → minimal localized placeholder; carry iii = ActorContext seam only).
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). This change touches none.
- **Staged sibling:** `substrate-hardening` (audit fixes incl. `php ^8.3`→`^8.5`) — keep composer churn out of THIS change except Pennant (3.1).

## Open Patterns
- **Read `progress.md` Codebase Patterns first.** Now NINE: *Fixed-set enum VO*, *Fail-closed VO factory*, *Immutable composite VO*, *Structural-invariant reflection test* (tests/ IS under phpstan-max — narrow via `assert`), *String-validated scalar VO*, *Policy-free composite VO + purity guard*, *Two-column Eloquent cast*, *Throwaway-table Feature test*, plus the new **Enum-as-SSOT, config derives** (2.1): enum is SSOT, `config/<x>.php` derives via static calls (`config:cache`-safe); **`config/` is NOT in `phpstan.neon` paths** (`app`/`database`/`routes`/`tests` only) → pin a config file that references app code with a **Feature** test (Pest boots TestCase only `->in('Feature')`). No `declare(strict_types)`, no `final` in `App\Platform` (house style).
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotation by size (~200KB); `memory-health.sh` warns at Stop. Closing ritual includes a LOCAL PostgreSQL verify (SQLite-green ≠ done).
- **Substrate (`App\Platform`)**: boundary law arch-test-enforced (prefix-matched — sub-namespaces free); recorder consumes payload arrays verbatim (never rehydrates Money); envelope UUIDv7 + minor-units + FX decimal-string; module identities cross as `string`.
