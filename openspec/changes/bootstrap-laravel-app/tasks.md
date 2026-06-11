## 1. Laravel Skeleton

- [ ] 1.1 Install Laravel at the repo root via temp-dir merge: `composer create-project laravel/laravel /tmp/newco-laravel-skeleton --no-interaction`, then `rsync -a --ignore-existing --exclude .git /tmp/newco-laravel-skeleton/ ./`, then merge Laravel's `.gitignore` into the existing root `.gitignore` (union; keep existing comments and rules), then `composer install` + `php artisan key:generate`.
  - Acceptance: `php artisan about` runs without error.
  - Acceptance: `git status` shows NO modification/deletion of pre-existing files (`README.md`, `CLAUDE.md`, `RALPH.md`, `ralph.sh`, `CONTEXT.md`, `hot.md`, `log.md`, `lessons.md`, `.claude/`, `openspec/`, `spec/`, `docs/`, `decisions/`, `knowledge/`) — except the intentional `.gitignore` merge.
- [ ] 1.2 Configure environments: `.env.example` committed with SQLite config + documented `OPERATOR_*` seeder vars; tests run on `sqlite :memory:`; `php artisan migrate` green on a fresh SQLite file; add a Pest feature test asserting `GET /up` returns 200.
  - Test hint: `it('responds healthy on /up')->get('/up')->assertStatus(200)`.

## 2. Quality Pipeline

- [ ] 2.1 Pint wired: config present (or explicit framework defaults), `composer format` and `composer lint` scripts added, both green on the codebase.
- [ ] 2.2 Pest confirmed as the test runner: example unit test + the health feature test green via `php artisan test`; `composer test` script added; verify the `test_filter` command from `CLAUDE.md` works with one named test.
- [ ] 2.3 Larastan wired: `phpstan.neon` at the highest level that passes without a baseline (target ≥ 8), `composer analyse` script, `vendor/bin/phpstan analyse` green.
- [ ] 2.4 Run all five Quality Commands from the `CLAUDE.md` table in order — all green; record exact installed versions (PHP, Laravel, Filament n/a yet, Pest, PHPStan, Pint) in `progress.md`.

## 3. Operator Panel & CI

- [ ] 3.1 Filament installed (`--no-interaction`): panel id `admin` at `/admin`; `OperatorSeeder` creating the operator from env vars; Pest feature tests: unauthenticated `GET /admin` redirects to the panel login, seeded operator can authenticate and reach the dashboard.
  - Test hint: use Filament's testing helpers (`livewire()`/`actingAs`) per Filament docs; factories for the operator user.
- [ ] 3.2 GitHub Actions `.github/workflows/ci.yml`: triggers on push + pull_request; PHP setup matching local minor version; composer cache; steps `vendor/bin/pint --test` → `vendor/bin/phpstan analyse` → `php artisan test` on SQLite; quality loop green locally before committing the workflow.
- [ ] 3.3 Write `docs/development.md` (clone → composer install → env → migrate → serve; the five Quality Commands; how to run/monitor `./ralph.sh`; exact installed versions) and update `docs/INDEX.md` row; re-run full quality commands one final time.
