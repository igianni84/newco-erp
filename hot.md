---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter — task 3.2 EXT-1 flag + accessor ✅ green; §3 feature flags COMPLETE 2/2, 11 of 14 tasks done).** Added `App\Platform\Features\FeatureFlag` (string-backed enum, typed name SSOT — case `NftOnChain='nft-on-chain'`, each case carries its launch default via exhaustive `defaultState()` match) + `App\Platform\Features\Features` accessor (`define()` registers every case with Pennant; `active(FeatureFlag): bool`). Registered globally in `AppServiceProvider::boot()` via `Features::define()`. Documented EXT-1 + NS-path-as-universal-fallback in new `docs/feature-flags.md` (+ INDEX row). **No composer churn this iter** (Pennant was 3.1 only).

## Build & Quality Status
- Stack: PHP 8.5.2 runtime · Laravel 13.15.0 (^13.8) · Filament 5.6.7 · Pennant v1.23.0 (^1.23) · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump staged in `substrate-hardening`.)
- Branch `ralph/foundations-money-i18n-flags`: suite **226/226** green (was 218, +8) · phpstan **0** @ max · pint clean · `openspec validate --strict` valid. Working-tree composer files unchanged this iter; `git diff main` composer = Pennant-only (from 3.1).

## Active Change & Next Task
- **In progress: `foundations-money-i18n-flags` — 11 of 14 tasks done.** §1 Money VOs 5/5; §2 i18n 4/4; §3 feature flags **2/2 COMPLETE**.
- **NEXT = task 4.1 `ActorContext` seam** (design D6) — a `(ActorRole, ?int $actorId)` resolver in `App\Platform\Events` (next to `ActorRole`): default `(System, null)`; `runAs(ActorRole, ?int, callable)` scoped override that restores the prior context after; **reads NO auth state**, imports no auth/Filament/`App\Modules` code (stays the safe side of the identity/auth ADR gate). Refactor the existing platform demo (+ any seeder) to obtain its role via `ActorContext` instead of literal `ActorRole::System` (behaviour-preserving — the F1 2/3 demo + pipeline tests must stay green unchanged). Test `tests/Unit/Platform/Events/ActorContextTest.php` (Feature if a container binding): default→System/null; `runAs(NewcoOps,42)` yields that inside, restores after; gate-safe case `actingAs(User::factory()->make())` then `current()` still System (auth ignored) — make non-vacuous with an arch-style assertion that `ActorContext` does NOT import `Illuminate\Support\Facades\Auth`/`App\Modules`. The `ActorRole` enum + the NOT-NULL envelope column are untouched.
- Then §5 docs/sweep: 5.1 (forward-ref cleanup in `docs/event-substrate.md` + `DomainEventRecorder` docblock → present tense pointing at `App\Platform\Money`; `GUIDE.md` F1 3/3 done; `CONTEXT.md` glossary incl. Money/Currency/FX/DualCurrency/Supported locale/Translatable text/**Feature flag·EXT-1**/Actor context; doc-pins incl. `docs/feature-flags.md`) → 5.2 (traceability + scenario→test mapping table + final green sweep + composer-diff = Pennant-only check).

## Blockers & Decisions Needed
- None active. Founder default calls stand (design Open Questions 1–3).
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). 4.1 ActorContext is deliberately the gate-SAFE seam (reads no auth) — does NOT step the identity/auth gate.
- **Staged sibling:** `substrate-hardening` (audit fixes incl. `php ^8.3`→`^8.5`) — keep all composer churn out of THIS change (Pennant 3.1 was the one allowed addition, done).

## Open Patterns
- **Read `progress.md` Codebase Patterns first** (~19 templates). NEW this iter: **Pennant feature flag — typed enum registry + global accessor** (enum-over-const for type-safe no-magic-strings; 0-param resolver = global feature; `active()` ≡ `!== false`; **defined()-membership non-vacuity** guard since undefined names also read false; boot-time registration; doc the NS-fallback). Reuse for future D12 gates.
- **Gotchas:** Pennant undefined feature → `false` (no throw, no row) so pair every "off" assert with `defined()`/`cases()` membership. `Feature::discover()` NOT auto-called (scans `app/Features` only). `config/` outside PHPStan → config-pin via Feature test. HTTP Feature tests use `Pest\Laravel\*` typed globals, never `$this->`.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotation by size (~200KB); `memory-health.sh` warns at Stop. Closing ritual includes a LOCAL PostgreSQL verify (SQLite-green ≠ done).
