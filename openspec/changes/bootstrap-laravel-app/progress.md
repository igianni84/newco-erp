# Progress — bootstrap-laravel-app

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Installed stack (tasks 1.1–1.2, 2026-06-11):** Laravel **13.15.0** · PHP **8.5.2** · Composer **2.9.2** · **Pest 4.7** (+ pest-plugin-laravel 4.1, installed in 1.2; coexists with skeleton phpunit/phpunit ^12.5.12 in require-dev), laravel/pint ^1.27, and a `laravel/pao` dev package (new in the 13.x skeleton). DB = SQLite (`database/database.sqlite`, gitignored); default drivers: cache/queue/session = database. Tests run on sqlite `:memory:` (phpunit.xml) — pinned by `tests/Feature/EnvironmentTest.php`.
- **Pest specifics:** `php artisan pest:install` does NOT exist in pest-plugin-laravel 4.x — scaffold with `vendor/bin/pest --init` (creates only `tests/Pest.php`, leaves phpunit.xml/TestCase/existing tests untouched). PHPUnit-class tests run fine under Pest. `php artisan test` auto-delegates to Pest once installed. Feature tests bind `Tests\TestCase` via `pest()->extend()` in `tests/Pest.php`; apply `RefreshDatabase` per-file with `uses(...)`.
- **`OPERATOR_*` env contract (defined 1.2):** `OPERATOR_NAME` / `OPERATOR_EMAIL` / `OPERATOR_PASSWORD` in `.env.example` (placeholders only, password empty) — task 3.1's `OperatorSeeder` must read exactly these names.
- **Quality-command output is hook-wrapped:** pint/phpunit output arrives as one-line JSON like `{"tool":"pint","result":"passed"}` — parse that, don't expect vanilla CLI output.
- **Root `.gitignore` is a curated union** (our infra rules + Laravel skeleton section appended at the bottom). Skeleton entries subsumed by our globs (`.env.*`, `/vendor/`, `/node_modules/`) were deliberately NOT duplicated. Extend the bottom section if new tooling needs ignores.
- **PHPStan not installed yet** → quality-loop step 4 (type_check) is skipped until task 2.3 wires Larastan.
- **Composer quality aliases (2.1):** scripts reference the bare binary name (`"format": "pint"`, `"lint": "pint --test"`) — Composer prepends `vendor/bin` to PATH, so no `vendor/bin/` prefix or `@php` needed. They mirror the `CLAUDE.md` Quality Commands table verbatim (format = fix, lint = `--test` check-only). `composer analyse` (2.3) + the already-present skeleton `test` (2.2) complete the set. `tests/Feature/QualityToolingTest.php` pins these script keys + the pint preset so a later `composer require` (Filament 3.1, Boost 3.2) that rewrites `composer.json` can't silently drop them — re-run it after any `composer require`.
- **Pint config:** `pint.json` = `{"preset":"laravel"}` — the framework default made explicit (a documented home for future rule additions), not a custom ruleset. Pint 1.29.1.

---

## [2026-06-11 10:51] — 1.1 Install Laravel 13.x at the repo root via temp-dir merge
- What was implemented:
  - `composer create-project laravel/laravel:^13.0 /tmp/newco-laravel-skeleton --no-interaction` → Laravel **13.15.0**.
  - `rsync -a --ignore-existing --exclude .git /tmp/newco-laravel-skeleton/ ./` — zero pre-existing files modified/deleted (verified via `git status --porcelain`: only ` M .gitignore`, which is the intentional merge).
  - `.gitignore` union merge: kept all existing comments/rules, appended a "Laravel skeleton defaults" section with the 16 entries not already covered by our globs (`*.log`, `.phpunit.result.cache`, `/.idea`, `/auth.json`, `/storage/pail`, `_ide_helper.php`, …).
  - `composer install` + `php artisan key:generate` at root; temp dir removed afterwards.
- Files changed: `.gitignore` (merge) + new skeleton files (`app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, `artisan`, `composer.json`, `composer.lock`, `package.json`, `phpunit.xml`, `vite.config.js`, `.editorconfig`, `.gitattributes`, `.npmrc`, `.env.example`).
- Quality loop: **green** — format ✅ · test_filter (ExampleTest) ✅ · full test ✅ (2 tests, 2 assertions) · type_check skipped (PHPStan arrives in 2.3) · lint ✅. `openspec validate bootstrap-laravel-app --strict` ✅.
- Acceptance walked: `php artisan about` reports Laravel 13.15.0 ✅; `git status` shows no pre-existing file modified/deleted except `.gitignore` ✅; `.env`/`vendor/`/`database.sqlite`/`.DS_Store` all confirmed git-ignored ✅.
- **Learnings for future iterations:**
  - Laravel 13 skeleton's create-project post-scripts already run key:generate + sqlite touch + `migrate --graceful` inside the temp dir; rsync then carries the migrated sqlite + `.env` over (both ignored).
  - Skeleton ships PHPUnit, NOT Pest — task 2.2 must install/convert to Pest (`pestphp/pest` + convert the two ExampleTests), it is not a no-op "confirmation".
  - Skeleton `.gitignore` had 16 entries our root file lacked; future tooling ignores go in the bottom section of root `.gitignore`.
---

## [2026-06-11 10:59] — 1.2 Configure environments
- What was implemented:
  - **Pest installed here, not in 2.2** (decision): task text demands "a Pest feature test", so installed the decided stack minimally — `composer require pestphp/pest pestphp/pest-plugin-laravel --dev --with-all-dependencies` → Pest **4.7** / plugin **4.1**, no conflict with skeleton phpunit ^12.5.12. Scaffolded `tests/Pest.php` via `vendor/bin/pest --init` (the `pest:install` artisan command does not exist in plugin 4.x).
  - `.env.example` + local `.env`: appended documented `OPERATOR_NAME`/`OPERATOR_EMAIL`/`OPERATOR_PASSWORD` block (placeholders, empty password — no secrets committed); SQLite config was already the skeleton default and retained.
  - `tests/Feature/HealthCheckTest.php` (Pest): `it('responds healthy on /up')` → 200 (exact hint name) + 404 edge case on unknown path.
  - `tests/Feature/EnvironmentTest.php` (Pest, `RefreshDatabase`): pins testing DB = sqlite `:memory:` (config assertions) and proves migrations run in-memory (`users`/`cache`/`jobs` tables exist).
  - Fresh-file migrate demonstrated: `rm database/database.sqlite && touch && php artisan migrate` → exit 0, all 9 framework tables.
- Files changed: `.env.example`, `composer.json`, `composer.lock`, `tests/Pest.php` (new), `tests/Feature/HealthCheckTest.php` (new), `tests/Feature/EnvironmentTest.php` (new), `tasks.md`, `progress.md`.
- Quality loop: **green** — format ✅ · test_filter ("responds healthy on /up") ✅ · full test ✅ (6 tests / 9 assertions: 2 skeleton PHPUnit-style + 4 new Pest) · type_check skipped (2.3) · lint ✅ · `openspec validate --strict` ✅.
- **Learnings for future iterations:**
  - **Task 2.2 scope is now:** confirm runner (`"tool":"pest"` in hook output already proves delegation), add `composer test` script, verify `test_filter` per CLAUDE.md, optionally convert the two ExampleTests to Pest syntax. Install is DONE.
  - The hook-wrapped runner output switched from `{"tool":"phpunit",...}` to `{"tool":"pest",...}` automatically — `php artisan test` detects Pest.
  - `--no-interaction` on `vendor/bin/pest --init` works (the GitHub-star prompt answers itself with the default).
---

## [2026-06-11 12:00] — 2.1 Pint wired
- What was implemented:
  - **`pint.json`** = `{"preset": "laravel"}` — explicit framework default (decision: the task allowed "config present (or explicit framework defaults)"; chose an explicit file as a documented home for future rules over relying on Pint's implicit default). Pint 1.29.1.
  - **`composer.json` scripts** — added `"format": "pint"` and `"lint": "pint --test"` (grouped just before the skeleton `test` alias). Mirror the `CLAUDE.md` Quality Commands table exactly: format = auto-fix, lint = check-only.
  - **`tests/Feature/QualityToolingTest.php`** (Pest, 3 tests) — regression guard: asserts `scripts.format`/`scripts.lint` exist and reference `pint` (+ `--test` for lint), and that `pint.json` pins `preset: laravel`. Normalises string-or-array script forms via a `composerScript()` helper.
- Files changed: `pint.json` (new), `composer.json` (+2 scripts), `tests/Feature/QualityToolingTest.php` (new), `tasks.md`, `progress.md`.
- Quality loop: **green** — format ✅ · test_filter (`QualityToolingTest`, 3/7) ✅ · full test ✅ (9 tests / 16 assertions) · type_check skipped (Larastan = 2.3) · lint ✅ · also ran the actual `composer format` + `composer lint` aliases (both `{"tool":"pint","result":"passed"}`) · `openspec validate --strict` ✅.
- Acceptance walked: config present (`pint.json`) ✅; `composer format` + `composer lint` scripts added ✅; both green on the codebase ✅.
- **Learnings for future iterations:**
  - Composer resolves bare binary names against `vendor/bin` (PATH is extended for scripts) — `"format": "pint"` needs no `vendor/bin/` prefix or `@php` wrapper.
  - `QualityToolingTest` must stay green through tasks 3.1 (Filament) and 3.2 (Boost): both run `composer require`, which can rewrite `composer.json` — if the quality scripts vanish from the rewrite, this test catches it.
  - Task 2.2 remaining scope is unchanged (already noted in 1.2): `composer test` alias already exists from the skeleton; remaining is `test_filter` verification + optional ExampleTest→Pest conversion. `composer analyse` is task 2.3.
---
