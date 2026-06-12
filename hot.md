---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 10:57 (ralph — foundations-modules-skeleton 1.2)** — **Task 1.2 green: nine module service providers + registry-driven registration.** Each `app/Modules/{Name}/Providers/{Name}ServiceProvider extends Illuminate\Support\ServiceProvider`, empty `register()/boot()` = the wiring seam (design D1); idiom matches `AppServiceProvider` (no `declare(strict_types=1)`, class docblock). OperatorPanel gets the standard `OperatorPanelServiceProvider` (distinct from the Filament `AdminPanelProvider`, D5). `bootstrap/providers.php` registers the nine **derived from the registry**: `...array_map(fn (Module $m) => $m->providerClass(), Module::cases())` after the kept `AppServiceProvider` + `AdminPanelProvider` (panel relocates in 1.3, not now). Added `Module::providerClass()` (`{namespace}\Providers\{Name}ServiceProvider`) so composition root AND conformance test derive the FQCN from one source; pinned in unit `ModuleTest` (Catalog+OperatorPanel). New `tests/Feature/Modules/ModuleProvidersTest.php` (3 tests, iterate `Module::cases()`). Progress 2/9 tasks.

## Build & Quality Status
- **Stack invariato** (zero composer churn — this change adds NO deps): PHP 8.5.2 · Laravel 13.15.0 (`^13.8`) · Filament v5.6.7 + Livewire v4.3.1 · Pest 4.7.2 (incl. pest-plugin-arch, already in lock) · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`.
- Quality loop (post-1.2): format ✅ · filtered 9/9 ✅ · **full suite 45/45 (135 assertions)** ✅ (was 41/105) · type_check 0 @ level max ✅ · lint ✅ · `php artisan about` exit 0 ✅ · `openspec validate foundations-modules-skeleton --strict` ✅. `git diff main -- composer.json composer.lock` empty.

## Active Change & Next Task
- **`foundations-modules-skeleton` (F1 1/3), branch `ralph/foundations-modules-skeleton`, 2/9 tasks done.**
- **Next task: 1.3** — relocate the Filament panel into its module (design D5): move `app/Providers/Filament/AdminPanelProvider.php` → `app/Modules/OperatorPanel/Providers/AdminPanelProvider.php` (namespace `App\Modules\OperatorPanel\Providers`; **class body unchanged** — panel id `admin`, path `admin`, `->login()`, default Dashboard), update the `use` in `bootstrap/providers.php`, delete the now-empty `app/Providers/Filament/` dir. The pre-existing `tests/Feature/OperatorPanelTest.php` + `OperatorSeederTest.php` MUST pass **unmodified** — run before & after. Extend `ModuleProvidersTest`: `class_exists(\App\Modules\OperatorPanel\Providers\AdminPanelProvider::class)` true & loaded; `class_exists('App\Providers\Filament\AdminPanelProvider')` false; `admin` panel registered (verify `Filament\Facades\Filament::getPanel('admin')` in vendor before asserting).
- **Then:** 2.1–2.4 arch tests (RED-PROOF mandatory, recorded in progress.md) → 3.1 `docs/module-template.md` → 4.1 sweep + scenario→test map. On `CHANGE_COMPLETE`: GUIDE §2.7 ritual (human reviews/merges/archives — ralph never pushes).

## Blockers & Decisions Needed
- None for this change. All names verified against repo/vendor; no protected files touched; no open ADR gate stepped into by the skeleton.
- **Carry-over (not this change):** human edits to CLAUDE.md from ADR-1/ADR-2 sessions (if not yet applied); semantic-verify debts W1/W2/W3/S1/S3 from bootstrap (bonify before staging / Module K gate). Open ADR gates: identity/auth (K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (F7) · frontend TanStack (Module S).

## Open Patterns
- **Registry is the single source of "the nine".** Every conformance/arch test AND the composition root iterate `Module::cases()` — never a hardcoded list. `->namespace()` for the FQCN root, `->value` for the table prefix, `->letter()` for the spec letter, `->providerClass()` for the standard provider FQCN.
- **Providers are derived, not listed.** `bootstrap/providers.php` spreads `Module::cases()`→`providerClass()`; a bad FQCN there fails LOUD (app can't boot → whole Feature suite errors). Standard module provider `extends Illuminate\Support\ServiceProvider` (NOT Filament `PanelProvider`).
- **Boot-time wiring assertions:** `app()->getLoadedProviders()` = `[FQCN => true]` (`Application.php:1477`; `providerIsLoaded(string)` at :1488). Pest `toHaveKeys`/`not->toHaveKey` (`Mixins/Expectation.php:628/661`).
- **Unit vs Feature:** `tests/Pest.php` binds `Tests\TestCase` only to `Feature`; pure-PHP tests (enum API) go in `tests/Unit/**` on plain PHPUnit `TestCase` (no boot).
- **App-file idiom:** no `declare(strict_types=1)`; Pint laravel preset (no spaces around `.`); exhaustive `match($this)` over the enum (no `default`).
- **Arch tasks (2.1–2.4) ahead:** each REQUIRES a red-proof (temp violating fixture → suite red → remove → green, both outputs in progress.md); verify pest-plugin-arch API in `vendor/pestphp/pest-plugin-arch/src/` before writing expectations.
- Full prior-phase patterns: `openspec/changes/archive/2026-06-11-bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
