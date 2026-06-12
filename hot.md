---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 11:05 (ralph — foundations-modules-skeleton 1.3)** — **Task 1.3 green: Filament panel relocated into its owning module (design D5).** New `app/Modules/OperatorPanel/Providers/AdminPanelProvider.php` (namespace `App\Modules\OperatorPanel\Providers`), **class body byte-for-byte unchanged** (panel id/path `admin`, `->login()`, Amber, default `Dashboard`; the three string-based `discoverResources/Pages/Widgets` still point at platform `app/Filament/*` — strings ≠ symbol imports, allowed by D5/D3; those dirs don't exist, Filament tolerates). `bootstrap/providers.php` `use` updated; old `app/Providers/Filament/AdminPanelProvider.php` `git rm`'d + empty dir removed (`AppServiceProvider` stays). **Ran `composer dump-autoload`** to drop the stale non-authoritative classmap entry (old FQCN → deleted file → `class_exists` would `include` a missing file → warning → Pest failure); touches only gitignored `vendor/composer/autoload_*.php`, zero composer.json/lock churn. OperatorPanel now hosts BOTH providers (standard seam via the `Module::cases()` spread + explicit Filament `PanelProvider`). `ModuleProvidersTest` +3 pins. Progress 3/9 tasks.

## Build & Quality Status
- **Stack invariato** (zero composer churn — this change adds NO deps): PHP 8.5.2 · Laravel 13.15.0 (`^13.8`) · Filament v5.6.7 + Livewire v4.3.1 · Pest 4.7.2 (incl. pest-plugin-arch, already in lock) · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`.
- Quality loop (post-1.3): format ✅ · filtered 6/6 ✅ · **full suite 48/48 (139 assertions)** ✅ (was 45/135) · type_check 0 @ level max ✅ · lint ✅ · `php artisan about` exit 0 ✅ · `openspec validate foundations-modules-skeleton --strict` ✅. Regression net `OperatorPanelTest`+`OperatorSeederTest` 7/7 (23 assert) IDENTICAL pre/post-move, both files unmodified. `git diff main -- composer.json composer.lock` empty.

## Active Change & Next Task
- **`foundations-modules-skeleton` (F1 1/3), branch `ralph/foundations-modules-skeleton`, 3/9 tasks done.**
- **Next task: 2.1** — stand up the Architecture test suite (design D8): create `tests/Architecture/ModuleConformanceTest.php` (registry↔filesystem: directories directly under `app/Modules/` == exactly the nine `Module::cases()` names, plus `Module.php` as the only loose file — **set-equality**, not subset, so a missing AND an extra tenth dir both fail). Add an `Architecture` testsuite to `phpunit.xml` so `php artisan test` runs it by default; check whether `tests/Pest.php` needs a binding for the new dir (arch/conformance tests need NO Laravel boot — keep them off `Tests\TestCase`). `php artisan test --filter` must keep working. **RED-PROOF mandatory & recorded in progress.md:** temp `mkdir app/Modules/Warehouse` → suite red → remove → green, both outputs pasted.
- **Then:** 2.2 cross-module privacy arch test · 2.3 platform-direction arch test · 2.4 forward-binding `$table`-prefix convention (each REQUIRES its own red-proof, recorded). → 3.1 `docs/module-template.md` (9 sections, D7) + INDEX/development.md rows → 4.1 traceability sweep + scenario→test map. On `CHANGE_COMPLETE`: human reviews/merges/archives (ralph never pushes).

## Blockers & Decisions Needed
- None for this change. All names verified against repo/vendor; no protected files touched; no open ADR gate stepped into by the skeleton.
- **Carry-over (not this change):** human edits to CLAUDE.md from ADR-1/ADR-2 sessions (if not yet applied); semantic-verify debts W1/W2/W3/S1/S3 from bootstrap (bonify before staging / Module K gate). Open ADR gates: identity/auth (K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (F7) · frontend TanStack (Module S).

## Open Patterns
- **Registry is the single source of "the nine".** Every conformance/arch test AND the composition root iterate `Module::cases()` — never a hardcoded list. `->namespace()` FQCN root, `->value` table prefix, `->letter()` spec letter, `->providerClass()` standard provider FQCN.
- **Relocating/renaming any `App\**` class → `composer dump-autoload` same iteration.** Non-authoritative classmap keeps a STALE old-FQCN→deleted-file entry; `findFile()` checks classmap before PSR-4 and returns it without an existence check → `class_exists('Old\FQCN')` includes a missing file → warning → Pest failure. Dump regenerates it; vendor gitignored → zero churn. Verify: `php -r "require 'vendor/autoload.php'; var_dump(class_exists('Old\FQCN'));"` → `false`, no warning.
- **Arch tasks (2.1–2.4) ahead:** each REQUIRES a red-proof (temp violating fixture → suite red → remove → green, both outputs in progress.md); verify pest-plugin-arch API in `vendor/pestphp/pest-plugin-arch/src/` before writing expectations (D4 says: never from memory).
- **Boot-time wiring assertions:** `app()->getLoadedProviders()` = `[FQCN => true]` (`Application.php:1477`). Filament panel introspection for tests: `Filament\Facades\Filament::getPanel('admin')->getId()` (`FilamentManager.php:372`; `Panel::getId()` via `HasId`).
- **Unit vs Feature:** `tests/Pest.php` binds `Tests\TestCase` only to `Feature`; pure-PHP tests (enum API, arch/conformance) run on plain PHPUnit `TestCase` (no boot).
- **App-file idiom:** no `declare(strict_types=1)`; Pint laravel preset (no spaces around `.`); exhaustive `match($this)` over the enum (no `default`).
- Full prior-phase patterns: `openspec/changes/archive/2026-06-11-bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
