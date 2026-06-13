---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter — task 4.1 ActorContext seam ✅ green; §4 COMPLETE, 12 of 14 tasks done).** Added `App\Platform\Events\ActorContext` (design D6): default `(ActorRole::System, null)`; accessors `role()`/`actorId()`; sole mutator `runAs(ActorRole, ?int, callable): mixed` (save-prior + try/finally restore). Bound a **container singleton** in `AppServiceProvider::register()`. Refactored `DemoCommand` to method-inject it and read role/id at both emit sites (behaviour-preserving). **Gate-safe** — reads no auth, imports nothing auth/Filament/module; pinned behaviourally (`actingAs`→still System) + structurally (arch `not->toUse`). `ActorRole` enum + envelope column untouched. No composer churn this iter.

## Build & Quality Status
- Stack: PHP 8.5.2 runtime · Laravel 13.15.0 (^13.8) · Filament 5.6.7 · Pennant v1.23.0 (^1.23) · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump staged in `substrate-hardening`.)
- Branch `ralph/foundations-money-i18n-flags`: suite **233/233** green (+7) · phpstan **0** @ max · pint clean · `validate --strict` valid. `git diff main` composer = Pennant-only (from 3.1).

## Active Change & Next Task
- **`foundations-money-i18n-flags` — 12 of 14 done.** §1 Money 5/5 · §2 i18n 4/4 · §3 flags 2/2 · §4 ActorContext 1/1. Only **§5 docs/sweep** remains.
- **NEXT = task 5.1 forward-ref cleanup + glossary** (design D7): flip future-tense → present in `docs/event-substrate.md` + the `DomainEventRecorder` docblock ("F1 3/3 value objects **will** make a float unrepresentable" → present, pointing at `App\Platform\Money`); set `GUIDE.md` F1 status to **3/3 done**; extend `CONTEXT.md` (house format + `_Avoid_` lines) with Money, Currency, FX Rate, Dual-Currency Amount, Supported locale, Translatable text, Feature flag/EXT-1, **Actor context**; document the NS-fallback (3.2) + `lang/` group-file (2.2) conventions. **Do NOT** rewrite the two archived proposals. Doc-pins follow the sibling `tests/Feature/EventSubstrateDocsTest.php` idiom; check for an existing `CONTEXT.md`/`GUIDE.md` pin test to extend.
- Then **5.2 final traceability + sweep**: five Quality Commands in table order green; `validate --strict` green; walk every `#### Scenario:` across all four delta specs, record the scenario→test mapping table in `progress.md` (fill any gap); confirm composer diff = Pennant-only + no protected file touched → `<promise>CHANGE_COMPLETE</promise>`.

## Blockers & Decisions Needed
- None active. Founder default calls stand (design Open Questions 1–3).
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).
- **Staged sibling:** `substrate-hardening` (incl. `php ^8.3`→`^8.5`) — keep all composer churn out of THIS change.

## Open Patterns
- **Read `progress.md` Codebase Patterns first.** NEW: **Container-singleton scoped-context seam** (default state + accessors, no setter; `runAs` save-prior/try-finally → restore-on-throw + nesting free; singleton binding = observability contract; gate-safety pinned behaviourally + structurally; arch `not->toUse` works in a `tests/Feature/**` file).
- **Gotchas:** Pennant undefined feature → `false` (pair "off" asserts with `defined()`/`cases()`). `actingAs`/instance-method Feature tests use `Pest\Laravel\*` typed globals, never `$this->`. `config/` outside PHPStan → config-pin Feature test. `User::factory()->make()` = in-memory.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). Closing ritual includes a LOCAL PostgreSQL verify.
