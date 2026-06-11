# Progress — bootstrap-laravel-app

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Installed stack (task 1.1, 2026-06-11):** Laravel **13.15.0** · PHP **8.5.2** · Composer **2.9.2**. Skeleton ships **PHPUnit 12.5** (not Pest — swap/confirm is task 2.2), laravel/pint ^1.27, and a `laravel/pao` dev package (new in the 13.x skeleton). DB = SQLite (`database/database.sqlite`, gitignored); default drivers: cache/queue/session = database.
- **Quality-command output is hook-wrapped:** pint/phpunit output arrives as one-line JSON like `{"tool":"pint","result":"passed"}` — parse that, don't expect vanilla CLI output.
- **Root `.gitignore` is a curated union** (our infra rules + Laravel skeleton section appended at the bottom). Skeleton entries subsumed by our globs (`.env.*`, `/vendor/`, `/node_modules/`) were deliberately NOT duplicated. Extend the bottom section if new tooling needs ignores.
- **PHPStan not installed yet** → quality-loop step 4 (type_check) is skipped until task 2.3 wires Larastan.

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
