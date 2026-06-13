---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (ralph iter — task 5.1 forward-ref cleanup + glossary ✅ green; §5 now 1/2, 13 of 14 tasks done).** Flipped the two live forward-references to present tense now that F1 3/3 VOs exist (design D7): `docs/event-substrate.md` §2 + the `DomainEventRecorder` class docblock now point at `App\Platform\Money` (`Money`/`FxRate`/`DualCurrencyAmount`) instead of "the F1 3/3 value objects **will** make…". Marked **`GUIDE.md` F1 row 3/3 done** (✅ + "**F1 completata 3/3**", Italian voice). Extended **`CONTEXT.md`** with 8 glossary terms (house format + `_Avoid_` lines): Money/Currency/FX Rate/Dual-Currency Amount → *Compliance & Finance*; Actor context → *Events & Audit*; new **## Platform Foundations** section → Supported locale/Translatable text/Feature flag·EXT-1. Doc-pins: extended `EventSubstrateDocsTest` (+2) + new `FoundationsDocsTest` (8 tests). NS-fallback (`docs/feature-flags.md`, 3.2) + lang-convention (`docs/i18n.md`, 2.2) were already written — 5.1 just pins them. Zero composer churn; no protected/archived-proposal edit.

## Build & Quality Status
- Stack: PHP 8.5.2 runtime · Laravel 13.15.0 (^13.8) · Filament 5.6.7 · Pennant v1.23.0 (^1.23) · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17. (`composer.json` still `php ^8.3` — bump staged in `substrate-hardening`.)
- Branch `ralph/foundations-money-i18n-flags`: suite **243/243** green (+10) · phpstan **0** @ max · pint clean · `validate --strict` valid. `git diff main` composer = Pennant-only (from 3.1); this task touched no composer file.

## Active Change & Next Task
- **`foundations-money-i18n-flags` — 13 of 14 done.** §1 Money 5/5 · §2 i18n 4/4 · §3 flags 2/2 · §4 ActorContext 1/1 · §5 docs/sweep **1/2**. Only **5.2** remains.
- **NEXT = task 5.2 final traceability + quality sweep (LAST task).** Run the five Quality Commands in CLAUDE.md table order (format · test_filter · test · type_check · lint), all green; `openspec validate foundations-money-i18n-flags --strict` green; **walk EVERY `#### Scenario:` across the four delta specs** (`money`, `i18n`, `feature-flags`, `event-substrate` under `openspec/changes/foundations-money-i18n-flags/specs/`) and record the scenario→covering-test mapping table in `progress.md` (any uncovered scenario gets its test on the spot); confirm `git diff main -- composer.json composer.lock` is **Pennant-only**; confirm no protected file modified and the **PHP constraint unchanged** (`php ^8.3` stays — `^8.5` is `substrate-hardening`). It adds no new tests unless the mapping finds a gap. Then, since ALL 14 tasks are `- [x]`, re-verify every task's acceptance at a glance and reply exactly `<promise>CHANGE_COMPLETE</promise>` — do NOT archive or merge (humans do that post-review).

## Blockers & Decisions Needed
- None active. Founder default calls stand (design Open Questions 1–3).
- **Open ADR gates (do not step into):** identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).
- **Staged sibling:** `substrate-hardening` (incl. `php ^8.3`→`^8.5`) — keep all composer churn out of THIS change.

## Open Patterns
- **Read `progress.md` Codebase Patterns first.** NEW: **Doc-pin test reuses the global `developerDoc()` reader** (never redeclare → fatal; thin named wrappers per doc; source files pinnable too; `expect($s)->toContain(…)->not->toContain(…)` REDS PHPStan-max → split into a fresh `expect()`; "non-term absent" non-vacuity idiom; Pint leaves bare-FQCN docblock prose alone).
- **Gotchas:** Pennant undefined feature → `false` (pair "off" asserts with `defined()`/`cases()`). HTTP/instance-method Feature tests use `Pest\Laravel\*` typed globals, never `$this->`. `config/` outside PHPStan → config-pin Feature test. `User::factory()->make()` = in-memory.
- **Second brain:** append to log.md ONLY via `scripts/memlog.sh` (real clock); rotate by size (~200KB). Closing ritual includes a LOCAL PostgreSQL verify.
