---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph — `operator-console-catalog-master` 6.1 green, i18n close).** The console's i18n GUARD (the `__()` routing was already complete — seeded 2.1, extended 3.1–5.2). New DB-free Feature test `ProductMasterConsoleI18nTest` (15 tests): a **sink-anchored `token_get_all` scan** over all 4 `OperatorPanel\Filament` classes proving NO hardcoded literal at any user-facing copy sink (planted-fixture **non-vacuity** proof); an 11-key **IT-rendering dataset** (`App::setLocale('it')`); **EN per-key fallback** on `product_master.label`; the **IT ⊆ EN** baseline invariant. Made fallback real by removing the redundant English-invariant `label`/`plural_label` from `lang/it/operator_console.php` (DEC-127).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **995/995 green** (4979 assertions, +15 vs 5.2). phpstan 0; pint clean. composer untouched; no migrations; no protected files. 2 files: new `ProductMasterConsoleI18nTest.php` + `lang/it/operator_console.php`.
- **PG17:** N/A for 6.1 (DB-free — locale resolution + static tokenisation). 6.1 is the only task 2–6 with no PG17 bullet; the cross-engine close is 6.2.
- ⚠ Full suite/phpstan: `php -d memory_limit=-1 vendor/bin/pest` / `… phpstan analyse` — bare `php artisan test` OOMs at 128M. PG run (6.2): `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=secret php -d memory_limit=512M vendor/bin/pest <paths>` (container `postgres:17`, start+teardown per run).

## Active Change & Next Task
- **Active: `operator-console-catalog-master`** (APPROVED — **10/11** done).
- **Next 6.2 (FINAL):** the full demo-path **chain** feature test (`ProductMasterConsoleChainTest.php`) — create → submit(A) → self-approval-rejected(A) → activate(B, producer active) → single-retire-preserves-child → cascade-retire-sibling-subtree → reopen + producer-gate-blocked path. Assert every write `actor_role: newco_ops`; event set exactly `{ProductMasterCreated, ProductMasterActivated, ProductMasterRetired (+cascade *Retired)}`, **0** for submit/reject/reopen; 1.2 arch + 1.3 boundary green. Confirm `CONTEXT.md` carries the **Operator console** term. **Run the ENTIRE OperatorPanel + Catalog + arch/boundary on PostgreSQL 17** and record it (the PG17 close). All 11 `- [x]` → `<promise>CHANGE_COMPLETE</promise>` (do NOT archive/merge — humans do).

## Blockers & Decisions Needed
- None. `openspec validate … --strict` green; on branch `ralph/operator-console-catalog-master`.
- Resolved note: design L8 names `lang/{en,it}/catalog.php` but only `lang/en/catalog.php` exists (parties.php too is EN-only). Reused rejection bodies fall back per-key to EN under `it` (DEC-127); the IT Catalog/Parties groups are each module's own concern, out of scope.

## Open Patterns
- **i18n capability-close (NEW 6.1 — progress.md Codebase Patterns; reuse for the spine change):** sink-anchored `token_get_all` scan (anchor on copy-SINK methods, check first-arg token — IGNORES `make('x')`/field keys/routes/separators, no allow-list); NOT every-literal; NO `return '<literal>'` scan (false-positives `getView()`). Non-vacuity via a planted nowdoc (`<?php` at col 0). Per-key EN fallback works at NESTED depth; make it real by OMITTING English-invariant terms from `it`. Pin `IT ⊆ EN` via `array_diff(Arr::dot(it), Arr::dot(en))` keys.
- **Filament 5 lifecycle ACTION / CREATE page / read-only resource** (progress.md — read before 6.2): header-action `$record`/`$data` name-injection; `surfaceLifecycleOutcome` base-`\RuntimeException` catch → localized danger notification; affordance = `->requiresConfirmation()->modalDescription(__())`; `assertNotified` takes the TITLE only. Console surface = exactly {Models, Actions}: catch rejections by base type, render enums via instance (no `Catalog\Exceptions`/`Catalog\Enums` import).
