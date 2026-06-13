---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter — task 3.1 Install Pennant ✅ green; §3 feature flags 1/2, 10 of 14 tasks done).** `composer require laravel/pennant` → `^1.23` / locked **v1.23.0** (latest stable, clean vs Laravel 13.15.0). Published `config/pennant.php` + `database/migrations/2026_06_13_085902_create_features_table.php` (`vendor:publish --provider`; `publishesMigrations` rewrote the prefix to today). Migration **SQLite-clean** (verified `migrate --pretend`; no PG-only construct, no DB-ADR fallback). The ONLY composer-touching task; `php ^8.3` untouched.

## Build & Quality Status
- Stack: PHP 8.5.2 runtime · Laravel 13.15.0 (^13.8) · Filament 5.6.7 · **Pennant v1.23.0 (NEW, ^1.23)** · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump staged in `substrate-hardening`.)
- Branch `ralph/foundations-money-i18n-flags`: suite **218/218** green (was 215, +3) · phpstan **0** @ max · pint clean · `openspec validate --strict` valid. `git diff main -- composer.json composer.lock` = **Pennant-only** (no transitive bumps; `php ^8.3` intact).

## Active Change & Next Task
- **In progress: `foundations-money-i18n-flags` — 10 of 14 tasks done.** §1 Money VOs 5/5; §2 i18n 4/4; §3 feature flags **1/2** (3.1 Pennant installed).
- **NEXT = task 3.2 EXT-1 flag + accessor** (design D5) — define the `nft-on-chain` feature (GLOBAL, default `false`) + a reusable `App\Platform\Features\Features` accessor (typed name constant/enum + thin `active()` helper, no magic strings) + document the NS-path-as-universal-fallback convention in `docs/`. Covers the 2 remaining feature-flags scenarios (EXT-1 OFF by default; single named on-chain gate, serialization NOT gated). **Verify in `vendor/` first:** `Feature::define`/`active` global-feature + scope API, and WHERE to register it (a provider `boot()`; Pennant also auto-discovers `App\Features`). Test `NftFlagTest` (`RefreshDatabase`): accessor reports OFF with no stored value; canonical name constant resolves; an undefined flag name fails. NS-fallback pinned by the 5.1 docs test (no serialization workflow exists yet — don't fabricate one).
- Then §4 ActorContext seam (4.1) → §5 docs/sweep (5.1, 5.2).

## Blockers & Decisions Needed
- None active. Founder default calls stand (design Open Questions 1–3).
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). This change touches none.
- **Staged sibling:** `substrate-hardening` (audit fixes incl. `php ^8.3`→`^8.5`) — Pennant (3.1) was the one allowed dep addition, now done; keep all other composer churn out of THIS change.

## Open Patterns
- **Read `progress.md` Codebase Patterns first** (~18 templates). NEW this iter: **First-party package adoption** — require-no-constraint → `vendor:publish --provider` → `migrate --pretend` SQLite check → record version in BOTH `docs/development.md` AND `DevelopmentDocsTest` `$packages` pin → config-pin test → container/facade proof. Reuse for Horizon at the queue ADR.
- **Gotchas:** `publishesMigrations` rewrites a published package migration's prefix to NOW (sorts after existing). `config/` is outside PHPStan paths → its only static guard is a Feature config-pin test.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotation by size (~200KB); `memory-health.sh` warns at Stop. Closing ritual includes a LOCAL PostgreSQL verify (SQLite-green ≠ done).
