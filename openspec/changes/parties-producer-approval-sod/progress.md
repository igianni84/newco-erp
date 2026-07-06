# Progress — parties-producer-approval-sod

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Full suite = `php -d memory_limit=-1 vendor/bin/pest`, NOT `php artisan test`.** The `artisan test` path (laravel/pao driver) spawns the runner in a subprocess capped at 128 MB and OOMs during result collection on the full ~1956-test suite (fatal in `DemoSeeder.php`, misleading line number — it dies while autoloading a PHPUnit class, not in a test). `-d memory_limit=1G` on `artisan` does NOT propagate to the subprocess. Run Pest directly with uncapped memory; per-`--filter` runs are fine under `artisan test`.
- **Parties copy is per-key EN-fallback (DEC-127).** `lang/en/parties.php` is the authored baseline + final fallback; per-locale files (e.g. `lang/it/parties.php`) may cover a *subset* and fall back to EN per key (`config('app.fallback_locale') = 'en'`). A new IT file need only carry the keys it translates. **Invariant:** every authored `it` key MUST have an `en` counterpart (`array_diff(Arr::dot(it), Arr::dot(en)) === []`) — assert it or a typo'd IT key dangles without a fallback.
- **i18n copy test idiom (PHPStan-max-clean).** `trans($key, $replace, $locale)` is typed `array|string|null`; do NOT chain `->toBeString()->not->...` off it (PHPStan can't resolve `->not` on the union). Use `expect(trans(...))->not->toBe($key)->and(trans(...))->toContain(...)`, and for authored-IT proof `expect(Lang::has($key,'it',false))->toBeTrue()->and(trans($key,[],'it'))->not->toBe(trans($key,[],'en'))` (the ProductMasterConsoleI18nTest pattern). Unit copy tests must `uses(TestCase::class)` explicitly (Pest auto-binds TestCase only in `Feature`).
- **Copy keys mirror Catalog's `approval` group, minus the reviewer leg.** Catalog uses `requires_operator` / `self_approval_creator` / `self_approval_reviewer` / `insufficient_separation`; Parties (linear FSM, no reviewer) uses only `requires_operator_principal` / `creator_may_not_approve`. Keep `:entity` parameterized (the guard passes `'Producer'`), name only the violated rule, never PII.

---

## [2026-07-06 11:43] — 1.1 Parties SoD approval copy (EN + IT)
- **What:** Added the `approval` copy group (keys `requires_operator_principal`, `creator_may_not_approve`) to `lang/en/parties.php` and created `lang/it/parties.php` with authored Italian for the same two keys — the localized messages the task-1.2 `SeparationOfDutiesViolation` factories will resolve. Wording mirrors `lang/en/catalog.php`'s `approval` group minus the reviewer leg (Producer FSM is linear). Fixed the now-stale "there is no lang/it/parties.php" parenthetical in the `compliance_review` comment.
- **Files changed:** `lang/en/parties.php` (+`approval` group, comment fix), `lang/it/parties.php` (new, `approval` group only), `tests/Unit/Modules/Parties/PartiesApprovalCopyTest.php` (new, 5 tests / 13 assertions).
- **Quality loop:** green. Pint (touched + full `--test`) clean · filter test 5/5 · full suite **1956/1956** (10432 assn, was 1951 + 5) via `php -d memory_limit=-1 vendor/bin/pest` · PHPStan max **0 errors** · `openspec validate parties-producer-approval-sod --strict` green. SQLite only this task (no schema/PG-relevant code; PG17 run belongs to the close ritual per design Migration Plan).
- **Learnings for future iterations:**
  - The `artisan test` OOM landmine + the i18n idioms are consolidated in Codebase Patterns above — read them before the next task's quality loop.
  - Task 1.2 (`SeparationOfDutiesViolation`) resolves `parties.approval.requires_operator_principal` / `parties.approval.creator_may_not_approve` via `(string) __(...)`, exactly like `Catalog\Exceptions\ApprovalGovernanceViolation::build()` — mirror that class's factory shape (`requiresOperatorPrincipal(string $entity)`, `creatorMayNotApprove(string $entity)`).
  - Pre-existing uncommitted bookkeeping (`hot.md`, `log.md`, `docs/validation/Remediation_Tracker.md`) from the prior authoring/approval session rode into this commit as the memory envelope — the tracker still shows RM-08 🟡 (approved/building); task 6.1 flips it to ✅ at close.
---
