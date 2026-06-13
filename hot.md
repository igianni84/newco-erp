---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter — task 2.3 `TranslatableText` VO + `TranslatableTextCast` ✅ green; §2 i18n 3 of 4, 8 of 14 tasks done).** Shipped the per-row i18n-keyed-JSON primitive (DEC-064): `App\Platform\I18n\TranslatableText` (`of()` validates every locale key; `resolve(?string)` = per-attribute English fallback, **pure** — null locale → registry fallback, NOT `App::getLocale()`; `JsonSerializable` + static `fromJson()` round-trip) + `TranslatableTextCast` (single JSON column). 4 files (2 src + 2 tests, 12 tests).

## Build & Quality Status
- Stack: PHP 8.5.2 runtime · Laravel 13.x (^13.8) · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump staged in `substrate-hardening`.)
- Branch `ralph/foundations-money-i18n-flags`: suite **212/212** green (was 200, +12) · phpstan **0** @ max · pint clean · `openspec validate --strict` valid. `git diff main -- composer.json composer.lock` **empty** (Pennant lands only in task 3.1).

## Active Change & Next Task
- **In progress: `foundations-money-i18n-flags` — 8 of 14 tasks done.** §1 Money VOs **5/5**; §2 i18n **3/4** (2.1 registry + 2.2 lang/ + 2.3 TranslatableText done).
- **NEXT = task 2.4 `welcome.blade.php` remediation** (design D4, debt S3): replace the 72KB default page with a MINIMAL localized holding page whose visible strings ALL use `__('welcome.*')` (the `headline`/`tagline`/`coming_soon` keys authored in 2.2). **No routing/SPA/frontend-stack choice** (TanStack gate — landmine: do NOT fully localize). Test `WelcomePageTest`: `get('/')->assertOk()` + assert the rendered body has the resolved key VALUE (not a literal); a second non-`en` locale (where `tagline` is translated) shows the translated text. Keep it small — TanStack replaces it. Full detail in `progress.md`.
- Then §3 Pennant (3.1 = ONLY composer-touching task, `composer require laravel/pennant ^1.23` → 3.2 EXT-1 flag) → §4 ActorContext seam → §5 docs/sweep.

## Blockers & Decisions Needed
- None active. Founder-approved default calls stand (DualCurrencyAmount included; welcome.blade → minimal localized placeholder; carry iii = ActorContext seam only).
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). This change touches none.
- **Staged sibling:** `substrate-hardening` (audit fixes incl. `php ^8.3`→`^8.5`) — keep composer churn out of THIS change except Pennant (3.1).

## Open Patterns
- **Read `progress.md` Codebase Patterns first** (now ~16 named templates). NEW this iter: **Single-JSON-column cast + `JsonSerializable` VO owning its round-trip** (`set()` returns the serialised scalar — Laravel wraps non-array as `[$key=>…]`; `get()` reads raw `$value`; `json_encode(…, JSON_THROW_ON_ERROR)` narrows `string` @ phpstan-max) and **Pure-primitive resolution** (a resolver VO reads no app state → Unit-testable, as Pest binds TestCase only `->in('Feature')`). House style: no `declare(strict_types)`/`final` in `App\Platform`; tests/ IS under phpstan-max.
- **Gotcha (lessons.md):** Pint `fully_qualified_strict_types` turns a `{@see \Namespace}` docblock ref into a broken `use` — reference a concrete class or use prose.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotation by size (~200KB); `memory-health.sh` warns at Stop. Closing ritual includes a LOCAL PostgreSQL verify (SQLite-green ≠ done).
