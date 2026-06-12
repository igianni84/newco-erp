---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 11:05 (ralph — foundations-modules-skeleton 1.1)** — **Task 1.1 green: canonical module registry shipped.** `app/Modules/Module.php` = string-backed enum, nine cases `Catalog…OperatorPanel` (backing values snake_case `catalog…operator_panel` = the D6 table prefixes), `letter()` exhaustive `match($this)` → spec letters `0,K,A,D,S,B,C,E,Admin`, `namespace()` = `__NAMESPACE__.'\\'.$this->name` → `App\Modules\{CaseName}` (drift-proof). Test `tests/Unit/Modules/ModuleTest.php` (5 tests / 6 assertions): count=9, full letter map verbatim via strict `toBe`, full snake_case value map via `toBe` (pins D6 prefix contract for task 2.4), `namespace()` for Catalog+OperatorPanel, failure `Module::from('warehouse')`→`ValueError`. Unit suite runs on plain PHPUnit `TestCase` (no Laravel boot; the `tests/Pest.php` binding is `Feature`-only). Progress 1/9 tasks.

## Build & Quality Status
- **Stack invariato** (zero composer churn — this change adds NO deps): PHP 8.5.2 · Laravel 13.15.0 (`^13.8`) · Filament v5.6.7 + Livewire v4.3.1 · Pest 4.7.2 (incl. pest-plugin-arch, already in lock) · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`.
- Quality loop (post-1.1): format ✅ · filtered 5/5 ✅ · **full suite 41/41 (105 assertions)** ✅ (was 36/99) · type_check 0 @ level max ✅ · lint ✅ · `openspec validate foundations-modules-skeleton --strict` ✅. `git diff main -- composer.json composer.lock` empty.

## Active Change & Next Task
- **`foundations-modules-skeleton` (F1 1/3), branch `ralph/foundations-modules-skeleton`, 1/9 tasks done.**
- **Next task: 1.2** — create the nine `app/Modules/{Name}/Providers/{Name}ServiceProvider` (empty `register()/boot()`, the wiring seam, design D1) and register all nine in `bootstrap/providers.php` (KEEP existing `AppServiceProvider` + `AdminPanelProvider`; the panel relocates in 1.3, NOT 1.2). Test `tests/Feature/Modules/ModuleProvidersTest.php` iterating `Module::cases()`; verify the loaded-providers introspection API in vendor first (`Application::getLoadedProviders()` returns an array keyed by provider FQCN). Drive both files off `Module::cases()` + `->namespace()` — never hardcode the nine.
- **Then:** 1.3 (move `AdminPanelProvider`→OperatorPanel, pre-existing suite must pass UNMODIFIED) → 2.1–2.4 arch tests (RED-PROOF mandatory, recorded in progress.md) → 3.1 `docs/module-template.md` → 4.1 sweep + scenario→test map. On `CHANGE_COMPLETE`: GUIDE §2.7 ritual (human reviews/merges/archives — ralph never pushes).

## Blockers & Decisions Needed
- None for this change. All names verified against repo; no protected files touched; no open ADR gate is stepped into by the skeleton (substrate/migrations deferred to next change).
- **Carry-over (not this change):** human edits to CLAUDE.md from ADR-2 session (if not yet applied); semantic-verify debts W1/W2/W3/S1/S3 from bootstrap (bonify before staging / Module K gate). Open ADR gates: identity/auth (K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (F7) · frontend TanStack (Module S).

## Open Patterns
- **Registry-driven tests:** every architecture/conformance test iterates `Module::cases()` — NEVER a hardcoded module list — so module-set drift fails loudly. Use `->namespace()` for FQCN checks, `->value` for the table prefix, `->letter()` for the spec letter.
- **Unit vs Feature:** `tests/Pest.php` binds `Tests\TestCase` only to `Feature`; pure-PHP tests (enums, value objects) go in `tests/Unit/**` on plain PHPUnit `TestCase`, no boot.
- **Verbatim map assertion:** `expect($mapBuiltFromCases)->toBe([...])` — strict `===` pins completeness + values + order in one shot.
- **App-file idiom:** no `declare(strict_types=1)`; Pint laravel preset (no spaces around `.`); exhaustive `match($this)` over the enum (no `default` arm) so a missing case is a PHPStan hole.
- **Arch tasks (2.1–2.4) ahead:** each REQUIRES a red-proof (temp violating fixture → suite red → remove → green, both outputs in progress.md); verify pest-plugin-arch API in `vendor/pestphp/pest-plugin-arch/src/` before writing expectations.
- Full prior-phase patterns: `openspec/changes/archive/2026-06-11-bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
