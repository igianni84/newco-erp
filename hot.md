---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 11:27 (ralph — foundations-modules-skeleton 2.2)** — **Task 2.2 green: the cross-module privacy boundary law is now mechanically enforced (design D3).** New `tests/Architecture/ModuleBoundariesTest.php` (1 test, **144 assertions**): for every module M (`foreach (Module::cases())`) one `expect($M->namespace())->not->toUse($forbidden)->ignoring($publicSurface)` chain — `$forbidden` = the other eight modules' namespaces, `$publicSurface` = each other module's `\Contracts` + `\Events`. Both lists derived from `Module::cases()` (source filtered out), zero hardcoding. **pest-plugin-arch v4.0.2 API verified in `vendor/.../pest-plugin-arch/src/` BEFORE writing (D4)** and the multi-arg `not->toUse` semantics **empirically probed**: `not->toUse([list])` fails on ANY used namespace (not all) and the failure message **names the exact (source, target) pair**; `->ignoring([...])` strips uses by namespace PREFIX (`LayerFactory::make` → `str_starts_with`), so a Contracts/Events reference is allowed while any other internal reference still fails. **RED-PROOF recorded** in progress.md: (a) temp `app/Modules/Catalog/Tmp.php` → `Parties\Providers\PartiesServiceProvider` (internal) → RED naming Catalog→Parties; (b) swap the import to a temp `Parties\Contracts\TmpContract` interface (sole variable) → GREEN; both removed, `git status` clean. Covers delta-spec scenarios *"Cross-module internal import fails the suite"* + *"Public-surface import is allowed"*. Progress **5/9 tasks**.

## Build & Quality Status
- **Stack invariato** (zero composer churn — this change adds NO deps): PHP 8.5.2 · Laravel 13.15.0 (`^13.8`) · Filament v5.6.7 + Livewire v4.3.1 · Pest 4.7.2 incl. **pest-plugin-arch v4.0.2** (already in lock) · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`.
- Quality loop (post-2.2): format ✅ · filtered `--filter="keeps each module private"` 1/1 ✅ · **full suite 51/51 (285 assertions)** ✅ (was 50/141 — the arch test alone contributes 144) · type_check 0 @ level max ✅ · lint ✅ · `openspec validate foundations-modules-skeleton --strict` ✅. `git diff main -- composer.json composer.lock` empty. No protected files touched.

## Active Change & Next Task
- **`foundations-modules-skeleton` (F1 1/3), branch `ralph/foundations-modules-skeleton`, 5/9 tasks done.**
- **Next task: 2.3** — platform-direction arch test (design D3): the enumerated platform namespaces `App\Providers`, `App\Models`, `App\Http` must NOT use `App\Modules\*` (target `App\Modules` as a whole — any module symbol is a violation from platform code). **Extend the SAME file** `tests/Architecture/ModuleBoundariesTest.php` (separate expectation block). Keep the platform-namespace list a **single named constant/array** in the test (so the template can cite where to extend it). `bootstrap/providers.php` is outside `app/` classes → naturally out of arch scope, do NOT special-case it. **RED-PROOF mandatory:** temp class in `app/Providers/` importing `App\Modules\Catalog\Providers\CatalogServiceProvider` → FAILS → removed → green; record both outputs in progress.md.
- **Then:** 2.4 forward-binding `$table`-prefix convention (reflection over models, proven-empty scan today, red-proof ×3) → 3.1 `docs/module-template.md` (9 sections, D7) + INDEX/development.md rows → 4.1 traceability sweep + scenario→test map. On `CHANGE_COMPLETE`: human reviews/merges/archives (ralph never pushes).

## Blockers & Decisions Needed
- None for this change. All names verified against repo/vendor; no protected files touched; no open ADR gate stepped into by the skeleton.
- **Carry-over (not this change):** human edits to CLAUDE.md from ADR-1/ADR-2 sessions (if not yet applied); semantic-verify debts W1/W2/W3/S1/S3 from bootstrap (bonify before staging / Module K gate). Open ADR gates: identity/auth (K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (F7) · frontend TanStack (Module S).

## Open Patterns
- **Registry is the single source of "the nine".** Every conformance/arch test AND the composition root iterate `Module::cases()` — never a hardcoded list. `->namespace()` FQCN root, `->value` table prefix, `->letter()` spec letter, `->providerClass()` standard provider FQCN.
- **Import-boundary arch tests:** `expect($ns)->not->toUse([$forbidden])->ignoring([$allowed])` (pest-plugin-arch v4.0.2, verified in vendor — D4 never from memory). `not->toUse([list])` fails on ANY used namespace + names the (source,target) pair; `->ignoring([...])` excludes by namespace PREFIX (so `…\Contracts`/`…\Events` allow-list works); layer membership is prefix-based too and the nine module names are mutually non-prefixing. Build forbidden + ignore lists from `Module::cases()`. 2.3 extends the SAME `ModuleBoundariesTest.php`.
- **The Architecture suite is boot-free, registry-located, set-equality.** `tests/Architecture/` = own `<testsuite>` in `phpunit.xml` (→ default `php artisan test`; count delta is the proof) but NOT bound to `Tests\TestCase` in `tests/Pest.php` (arch/conformance/convention need no container). Root via reflection on `Module::class`, never `app_path()`. Type-clean at level max: guard `getFileName(): string|false` with `(string)`, `scandir(): array|false` with `?: []`. Filter dot-entries.
- **Each arch task (2.1–2.4) REQUIRES a red-proof** (temp violating fixture → suite red → remove → green, both outputs in progress.md). NEW app/ fixtures need no `composer dump-autoload` (PSR-4 resolves new paths live); dump-autoload is only for MOVED/RENAMED `App\**` classes (stale classmap → `class_exists` includes a deleted file → warning → Pest failure). Vendor gitignored → zero churn.
- **App-file idiom:** no `declare(strict_types=1)`; Pint laravel preset (no spaces around `.`); exhaustive `match($this)` over the enum (no `default`).
- Full prior-phase patterns: `openspec/changes/archive/2026-06-11-bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
