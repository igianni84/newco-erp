# Progress — foundations-modules-skeleton

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Module registry is the single source for "the nine modules".** `App\Modules\Module` (string-backed enum, `app/Modules/Module.php`): case name = namespace segment (`namespace()` → `App\Modules\{CaseName}`), backing value = snake_case table prefix (`->value`, e.g. `catalog` → table prefix `catalog_`, design D6), `letter()` = spec letter (`0,K,A,D,S,B,C,E,Admin`). Every architecture/conformance test MUST iterate `Module::cases()` — never a hardcoded module list — so module-set drift fails loudly.
- **Unit tests need no Laravel boot.** `tests/Pest.php` binds `Tests\TestCase` only to `Feature`; `tests/Unit/**` runs on plain PHPUnit `TestCase`. Pure-PHP tests (enums, value objects) belong in `tests/Unit/`. Pest `it()/expect()/->and()` globals work in both suites regardless of the binding.
- **Strong verbatim map assertion.** `expect($builtMap)->toBe([...])` uses strict `===` (order- + type-sensitive): asserts completeness, values, AND declaration order in one shot. Build the map by iterating `Module::cases()` keyed by `->name`, compare against the literal in spec order.
- **Quality loop is green-clean today:** `vendor/bin/pint <files>` → `php artisan test <path>` → `php artisan test` → `vendor/bin/phpstan analyse` (level max, 0 baseline) → `vendor/bin/pint --test`. App files follow Laravel idiom: no `declare(strict_types=1)`, Pint laravel preset (no spaces around `.` concatenation).

---

## [2026-06-12 11:05] — 1.1 canonical module registry enum
- **Implemented:** `App\Modules\Module` string-backed enum (design D2) — nine cases `Catalog…OperatorPanel` with snake_case backing values (`catalog`…`operator_panel`), `letter()` (exhaustive `match($this)` → spec letters `0,K,A,D,S,B,C,E,Admin`), `namespace()` (`__NAMESPACE__.'\\'.$this->name` → `App\Modules\{CaseName}`, drift-proof vs the file's own namespace).
- **Files changed:** `app/Modules/Module.php` (new), `tests/Unit/Modules/ModuleTest.php` (new, 5 tests / 6 assertions), `tasks.md` (1.1 ✓).
- **Tests:** count=9; full letter map verbatim via `toBe`; full snake_case value map via `toBe` (pins the D6 table-prefix contract task 2.4 depends on); `namespace()` for Catalog + OperatorPanel; failure case `Module::from('warehouse')` throws `ValueError`.
- **Quality loop:** green — format ✅ · filtered 5/5 ✅ · full suite 41/41 (105 assertions, was 36/99) ✅ · phpstan 0 @ level max ✅ · pint --test ✅ · `openspec validate --strict` ✅. composer.json/lock vs main: empty diff (zero new deps). No protected files touched.
- **Learnings for future iterations:**
  - The enum uses an **exhaustive `match($this)`** for `letter()` (no `default` arm): adding a tenth case without its letter is a PHPStan-detectable hole, not a silent fallthrough — keep it exhaustive.
  - `namespace()` uses `__NAMESPACE__` rather than a hardcoded `'App\Modules'` literal, so it can't drift from where the registry physically lives; tests pin the concrete output for two cases.
  - Next: 1.2 creates the nine `Providers/` dirs + `{Name}ServiceProvider`s and registers them in `bootstrap/providers.php` (keep `AppServiceProvider` + `AdminPanelProvider`; panel relocates in 1.3). Use `Module::cases()` + `->namespace()` to drive that test too.
---
