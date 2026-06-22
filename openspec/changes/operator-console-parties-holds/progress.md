# Progress — operator-console-parties-holds

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Full suite OOMs at PHP's default 128 MB memory_limit** — `php artisan test` / bare `vendor/bin/pest` abort with "Allowed memory size of 134217728 bytes exhausted" in `filament/…/routes/web.php` during result collection. Run the full suite as `php -d memory_limit=-1 vendor/bin/pest` (the same flag the PG17 ritual already uses). `--filter` runs and `phpstan analyse` are fine at the default.

---

## [2026-06-22 10:03] — 1.1 Relax the now-stale absence guards
- Dropped `assertActionDoesNotExist('placeHold')` + `('liftHold')` from `CustomerLifecycleConsoleTest` (the **only** test that held them — grep-confirmed across `tests/`); kept the `requireKyc` absence guard (KYC stays deferred → kyc-sanctions slice). Fixed the now-stale docblock + test title that claimed the Hold verbs are "deliberately ABSENT": the Hold place/lift surface lands in THIS slice but is pinned in the forthcoming `CustomerHoldsConsoleTest`, not in this status-verb file.
- Files changed: `tests/Feature/Modules/OperatorPanel/Parties/CustomerLifecycleConsoleTest.php` (test-only; no production code touched).
- Quality loop: **green** — `--filter=CustomerLifecycleConsoleTest` 9/9 (92 assn); full suite 1397/1397 (7679 assn, −4 vs 7683 baseline = the two removed guards × 2 internal assertions each, test count unchanged); phpstan max 0; pint clean; `openspec validate --strict` valid.
- **Learnings for future iterations:**
  - The placeHold/liftHold guards lived ONLY in `CustomerLifecycleConsoleTest`; no other console test referenced them. No record-screening/account/profile absence assertions exist yet to preserve — only `requireKyc` did.
  - Full suite needs `php -d memory_limit=-1` (see Codebase Patterns); the default 128 MB OOMs.
  - **Next: task 1.2** — pin the Filament 5 non-relation Holds table + per-row-action vehicle against the **installed** version (Filament 5.6.7), not from memory; record the chosen vehicle in the `ViewCustomer` docblock.
---
