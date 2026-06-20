# Progress — operator-console-catalog-master

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Running the FULL test suite:** `php artisan test` (and `composer test`) blow the box's default 128M PHP memory limit during `laravel/pao` result aggregation over the ~950-test suite — the `-d memory_limit` flag does NOT reach the pao worker. Run the full suite as **`php -d memory_limit=-1 vendor/bin/pest`** and PHPStan as **`php -d memory_limit=-1 vendor/bin/phpstan analyse`**. A *filtered* run (`php artisan test --filter=Foo`) stays under 128M and is fine for step 2.
- **Asserting Filament panel discovery (no resource needed):** `Filament::getPanel('admin')` exposes public getters `getResourceNamespaces()/getResourceDirectories()`, `getPageNamespaces()/getPageDirectories()`, `getWidgetNamespaces()/getWidgetDirectories()` — each returns the verbatim `for:` / `in:` args passed to `discoverResources/Pages/Widgets`. Use these to assert the discovery repoint directly (`->toContain('App\Modules\OperatorPanel\Filament\Resources')`) without scaffolding a real resource. Filament's `discoverComponents` silently no-ops on a missing directory (`HasComponents.php:496`), so an empty `.gitkeep` skeleton is a valid discovery target.
- **Operator-panel test auth:** authenticate with `actingAs(Operator::factory()->create(), 'operator')` (the `operator` session guard); `Operator` = `App\Modules\OperatorPanel\Models\Operator`. `get('/admin')->assertOk()` proves the panel boots + `canAccessPanel`.

---

## [2026-06-20 08:05] — 1.1 Repoint Filament discovery into the OperatorPanel module
- **What:** repointed `AdminPanelProvider::panel()` `discoverResources/discoverPages/discoverWidgets` from the shell's default `app_path('Filament/...')` (`App\Filament\*`) to `app_path('Modules/OperatorPanel/Filament/...')` with the matching `App\Modules\OperatorPanel\Filament\{Resources,Pages,Widgets}` namespaces (ADR 2026-06-19). Created the discovery skeleton `Filament/Resources/Catalog/`, `Filament/Pages/`, `Filament/Widgets/` (each a `.gitkeep`). No resource yet (1.x+).
- **Files changed:** `app/Modules/OperatorPanel/Providers/AdminPanelProvider.php` (modified); `app/Modules/OperatorPanel/Filament/{Resources/Catalog,Pages,Widgets}/.gitkeep` (new); `tests/Feature/Modules/OperatorPanel/PanelDiscoveryTest.php` (new, 4 tests / 10 assertions).
- **Quality loop:** green — format ✓; filtered ✓ (4/4); full suite ✓ **953/953** (4698 assertions, SQLite); phpstan ✓ (0 errors); pint --test ✓. `openspec validate --strict` ✓. composer.json/lock diff vs main empty; no migrations added; no protected files touched.
- **Acceptance walked:** panel boots at `/admin` (dashboard `assertOk` for an authed operator) ✓; discovery namespaces+directories repointed to the module (asserted via the panel getters, incl. a negative assertion the old `App\Filament\*` is gone) ✓; the `operator-auth-foundation` auth tests (login, password-reset routes, no-registration, opt-in TOTP, default guard) stay green ✓.
- **PG17:** not run this task — 1.1 touches no DB-writing action (panel-config + discovery only; the new test's lone DB use is `Operator::factory()` on the shared `operators` table). Cross-engine PG17 runs land on the DB-action tasks (2.1+) and the 6.2 close, per tasks.md.
- **Learnings for future iterations:**
  - The two patterns above (full-suite memory flag; panel-discovery getters) are now in Codebase Patterns — read them before running tests or asserting panel config.
  - Discovery dirs may be empty: Filament no-ops on a missing dir, so resources can land in `Filament/Resources/Catalog/` incrementally without touching `AdminPanelProvider` again.
  - `AdminPanelProvider` is app code (design L3) — editing it is in-bounds; `pages([Dashboard::class])` + `widgets([AccountWidget, FilamentInfoWidget])` are explicit framework registrations and were left intact.
---
