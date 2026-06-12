---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 11:17 (ralph — foundations-modules-skeleton 2.1)** — **Task 2.1 green: the always-on Architecture test suite stands up (design D8).** New `tests/Architecture/ModuleConformanceTest.php` (2 tests) pins the registry↔filesystem conformance (delta-spec scenario "No stray entries at the modules root"): the directories directly under `app/Modules/` are **exactly** the nine `Module::cases()` names (set-equality via `sort()`+`toBe` strict — symmetric: a missing AND an extra tenth dir both fail), and `Module.php` is the only loose file. `phpunit.xml` gains an `Architecture` `<testsuite>` so `php artisan test` runs it by default (proof: suite 48→**50** tests). `tests/Pest.php` left **UNCHANGED** — the check is boot-free, so it runs on the default PHPUnit `TestCase`. Modules root located by reflection (`dirname((string) (new ReflectionClass(Module::class))->getFileName())`), NOT `app_path()` — D8 no-boot, and immune to the container being flushed by a prior Feature test. Dot-entries filtered (`.DS_Store` gitignored → no macOS false-red). **RED-PROOF recorded** in progress.md: `mkdir app/Modules/Warehouse` → directories test red (`+9 => 'Warehouse'`, exit 1) → `rmdir` → 2/2 green, `git status` clean. Progress **4/9 tasks**.

## Build & Quality Status
- **Stack invariato** (zero composer churn — this change adds NO deps): PHP 8.5.2 · Laravel 13.15.0 (`^13.8`) · Filament v5.6.7 + Livewire v4.3.1 · Pest 4.7.2 (incl. pest-plugin-arch, already in lock) · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`.
- Quality loop (post-2.1): format ✅ · filtered 2/2 ✅ (+ `--filter="only loose file"` → 1/1, `test_filter` intact) · **full suite 50/50 (141 assertions)** ✅ (was 48/139) · type_check 0 @ level max ✅ (`tests/` analyzed; false-unions guarded) · lint ✅ · `openspec validate foundations-modules-skeleton --strict` ✅. `git diff main -- composer.json composer.lock` empty. No protected files touched.

## Active Change & Next Task
- **`foundations-modules-skeleton` (F1 1/3), branch `ralph/foundations-modules-skeleton`, 4/9 tasks done.**
- **Next task: 2.2** — cross-module privacy arch test (design D3), `tests/Architecture/ModuleBoundariesTest.php`. For every module M, code in `App\Modules\{M}` must NOT use any other module's namespace EXCEPT that target's `Contracts\*` and `Events\*`. One expectation per source module looping `Module::cases()` (no hardcoded lists) so a failure names the offending module; ignore-list for M = every other module's `\Contracts` + `\Events`. **Verify the pest-plugin-arch expectation API against `vendor/pestphp/pest-plugin-arch/src/` BEFORE writing** (D4: never from memory — confirm `expect('App\Modules\{M}')->not->toUse([...])->ignoring([...])` names). **RED-PROOF mandatory & recorded:** (a) temp `app/Modules/Catalog/Tmp.php` referencing `App\Modules\Parties\Providers\PartiesServiceProvider` → arch test FAILS naming the pair; (b) swap the reference to a temp `App\Modules\Parties\Contracts\TmpContract` interface → PASSES (public surface allowed); remove both, `git status` clean.
- **Then:** 2.3 platform-direction arch test (`App\Providers|Models|Http` must not use `App\Modules\*`; platform list as a single named constant) · 2.4 forward-binding `$table`-prefix convention (reflection over models, proven-empty scan today, red-proof ×3) — each its own red-proof. → 3.1 `docs/module-template.md` (9 sections, D7) + INDEX/development.md rows → 4.1 traceability sweep + scenario→test map. On `CHANGE_COMPLETE`: human reviews/merges/archives (ralph never pushes).

## Blockers & Decisions Needed
- None for this change. All names verified against repo/vendor; no protected files touched; no open ADR gate stepped into by the skeleton.
- **Carry-over (not this change):** human edits to CLAUDE.md from ADR-1/ADR-2 sessions (if not yet applied); semantic-verify debts W1/W2/W3/S1/S3 from bootstrap (bonify before staging / Module K gate). Open ADR gates: identity/auth (K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (F7) · frontend TanStack (Module S).

## Open Patterns
- **Registry is the single source of "the nine".** Every conformance/arch test AND the composition root iterate `Module::cases()` — never a hardcoded list. `->namespace()` FQCN root, `->value` table prefix, `->letter()` spec letter, `->providerClass()` standard provider FQCN.
- **The Architecture suite is boot-free, registry-located, set-equality.** `tests/Architecture/` = own `<testsuite>` in `phpunit.xml` (→ default `php artisan test`; count delta is the proof) but NOT bound to `Tests\TestCase` in `tests/Pest.php` (arch/conformance/convention need no container). Adding the `<testsuite>` suffices for discovery — Pest finds files from the phpunit dirs; `->in()` only picks the base class; `--filter` unaffected. Root via reflection on `Module::class`, never `app_path()`. Type-clean at level max: guard `getFileName(): string|false` with `(string)`, `scandir(): array|false` with `?: []`. "Exactly these" = sort both + `toBe` (symmetric: missing AND extra fail). Filter dot-entries.
- **Each arch task (2.1–2.4) REQUIRES a red-proof** (temp violating fixture → suite red → remove → green, both outputs in progress.md). Verify pest-plugin-arch API in `vendor/pestphp/pest-plugin-arch/src/` before writing expectations (D4: never from memory).
- **Relocating/renaming any `App\**` class → `composer dump-autoload` same iteration** (stale non-authoritative classmap → `class_exists('Old\FQCN')` includes a deleted file → warning → Pest failure). Vendor gitignored → zero churn.
- **Boot-time wiring assertions:** `app()->getLoadedProviders()` = `[FQCN => true]` (`Application.php:1477`). Filament panel introspection: `Filament\Facades\Filament::getPanel('admin')->getId()` (`FilamentManager.php:372`; `Panel::getId()` via `HasId`).
- **App-file idiom:** no `declare(strict_types=1)`; Pint laravel preset (no spaces around `.`); exhaustive `match($this)` over the enum (no `default`).
- Full prior-phase patterns: `openspec/changes/archive/2026-06-11-bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
