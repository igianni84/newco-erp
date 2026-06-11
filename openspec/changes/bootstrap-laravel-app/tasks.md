## 1. Laravel Skeleton

- [x] 1.1 Install Laravel 13.x at the repo root via temp-dir merge: `composer create-project laravel/laravel:^13.0 /tmp/newco-laravel-skeleton --no-interaction`, then `rsync -a --ignore-existing --exclude .git /tmp/newco-laravel-skeleton/ ./`, then merge Laravel's `.gitignore` into the existing root `.gitignore` (union; keep existing comments and rules), then `composer install` + `php artisan key:generate`.
  - Acceptance: `php artisan about` runs without error and reports Laravel 13.x.
  - Acceptance: `git status` shows NO modification/deletion of pre-existing files (`README.md`, `CLAUDE.md`, `RALPH.md`, `ralph.sh`, `CONTEXT.md`, `hot.md`, `log.md`, `lessons.md`, `.claude/`, `openspec/`, `spec/`, `docs/`, `decisions/`, `knowledge/`) — except the intentional `.gitignore` merge.
- [x] 1.2 Configure environments: `.env.example` committed with SQLite config + documented `OPERATOR_*` seeder vars; tests run on `sqlite :memory:`; `php artisan migrate` green on a fresh SQLite file; add a Pest feature test asserting `GET /up` returns 200.
  - Test hint: `it('responds healthy on /up')->get('/up')->assertStatus(200)`.

## 2. Quality Pipeline

- [x] 2.1 Pint wired: config present (or explicit framework defaults), `composer format` and `composer lint` scripts added, both green on the codebase.
- [ ] 2.2 Pest confirmed as the test runner: example unit test + the health feature test green via `php artisan test`; `composer test` script added; verify the `test_filter` command from `CLAUDE.md` works with one named test.
- [ ] 2.3 Larastan wired: `phpstan.neon` at the highest level that passes without a baseline (target ≥ 8), `composer analyse` script, `vendor/bin/phpstan analyse` green.
- [ ] 2.4 Run all five Quality Commands from the `CLAUDE.md` table in order — all green; record exact installed versions (PHP, Laravel, Filament n/a yet, Pest, PHPStan, Pint) in `progress.md`.

## 3. Operator Panel & CI

- [ ] 3.1 Filament 5.x installed (`composer require filament/filament:"^5.0"`, `--no-interaction`): panel id `admin` at `/admin`; `OperatorSeeder` creating the operator from env vars; Pest feature tests: unauthenticated `GET /admin` redirects to the panel login, seeded operator can authenticate and reach the dashboard.
  - Acceptance: `composer show filament/filament` reports a 5.x version.
  - Test hint: use Filament's testing helpers (`livewire()`/`actingAs`) per Filament docs; factories for the operator user.
- [ ] 3.2 Laravel Boost installed per Filament AI guidance (design.md "AI tooling"): `composer require laravel/boost --dev`, then `php artisan boost:install` selecting the Laravel + Filament guidelines (manual fallback per Boost/Filament docs if prompts block non-interactive use); reference https://filamentphp.com/docs/llms.txt in the docs task notes.
  - Acceptance: Boost guideline files / `AGENTS.md` present and committed; `git status` confirms NO modification to protected files (`CLAUDE.md`, `RALPH.md`, `ralph.sh`, `.claude/**` except `.claude/memory/`); all five Quality Commands still green.
- [ ] 3.3 GitHub Actions `.github/workflows/ci.yml`: triggers on push + pull_request; PHP setup matching local minor version; composer cache; steps `vendor/bin/pint --test` → `vendor/bin/phpstan analyse` → `php artisan test` on SQLite; quality loop green locally before committing the workflow.
- [ ] 3.4 Write `docs/development.md` (clone → composer install → env → migrate → serve; the five Quality Commands; how to run/monitor `./ralph.sh`; exact installed versions incl. Boost; link https://filamentphp.com/docs/llms.txt as the agent-facing Filament docs index) and update `docs/INDEX.md` row; re-run full quality commands one final time.
