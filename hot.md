---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter — task 2.4 `welcome.blade.php` remediation ✅ green; §2 i18n COMPLETE 4/4, 9 of 14 tasks done).** Replaced the 72KB default Laravel page with a MINIMAL localized holding page (~1.4KB rendered): `<title>`/`<h1>`=`__('welcome.headline')`, two `<p>`=`__('welcome.tagline')`/`__('welcome.coming_soon')` — keyed copy only (invariant 12). Plain inline `<style>`; **NO frontend stack** — dropped `@vite`/`@fonts`/Tailwind, no SPA, no routing change (TanStack landmine honoured). 2 files (blade + `WelcomePageTest`).

## Build & Quality Status
- Stack: PHP 8.5.2 runtime · Laravel 13.x (^13.8) · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump staged in `substrate-hardening`.)
- Branch `ralph/foundations-money-i18n-flags`: suite **215/215** green (was 212, +3) · phpstan **0** @ max · pint clean · `openspec validate --strict` valid. `git diff main -- composer.json composer.lock` **empty** (Pennant lands only in task 3.1).

## Active Change & Next Task
- **In progress: `foundations-money-i18n-flags` — 9 of 14 tasks done.** §1 Money VOs **5/5**; §2 i18n **4/4 COMPLETE** (2.1 registry + 2.2 lang/ + 2.3 TranslatableText + 2.4 welcome.blade).
- **NEXT = task 3.1 Install Pennant** (design D5) — the **ONLY composer-touching task**. `composer require laravel/pennant` (resolves `^1.23`, latest stable). Publish `config/pennant.php`; verify the published `features` migration is SQLite-clean (`:memory:`) — DB-ADR fallback path if a construct breaks SQLite. Record the EXACT version in `docs/development.md` + `progress.md`; commit `composer.lock`. Test `PennantInstalledTest` (`RefreshDatabase`): `Schema::hasTable('features')` + manager resolves from container. **Verify the published migration/table name in `vendor/` BEFORE pinning** (standing rule). If the resolver refuses a Laravel-13 Pennant → `HUMAN_NEEDED`, don't force. Full detail in `progress.md`.
- Then 3.2 EXT-1 `nft-on-chain` flag + `Features` accessor → §4 ActorContext seam (4.1) → §5 docs/sweep (5.1, 5.2).

## Blockers & Decisions Needed
- None active. Founder-approved default calls stand (DualCurrencyAmount included; welcome.blade → minimal localized placeholder; carry iii = ActorContext seam only).
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). This change touches none.
- **Staged sibling:** `substrate-hardening` (audit fixes incl. `php ^8.3`→`^8.5`) — keep composer churn out of THIS change except Pennant (3.1).

## Open Patterns
- **Read `progress.md` Codebase Patterns first** (~17 named templates). NEW this iter: **HTTP/instance-method Feature test under PHPStan max** — inside a Pest `it()` closure `$this` is statically `Pest\PendingCalls\TestCall`, so `$this->get()`/`->assertOk()` red phpstan (`method.notFound`/`method.nonObject`); use `pest-plugin-laravel` typed globals (`use function Pest\Laravel\get;`, `@return TestResponse`) for ALL future route/controller/Filament tests. Blade `{{-- … --}}` comments are stripped from output (safe to put `@`-words/key globs there).
- **Gotcha (lessons.md):** Pint `fully_qualified_strict_types` turns a `{@see \Namespace}` docblock ref into a broken `use` — reference a concrete class or use prose.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotation by size (~200KB); `memory-health.sh` warns at Stop. Closing ritual includes a LOCAL PostgreSQL verify (SQLite-green ≠ done).
