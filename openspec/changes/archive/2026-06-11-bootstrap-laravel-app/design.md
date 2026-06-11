## Context

Empty-of-code repo with protected infrastructure files at the root (`README.md`, `CLAUDE.md`, `RALPH.md`, `ralph.sh`, `CONTEXT.md`, memory files, `.claude/`, `openspec/`, `spec/`, `docs/`, `decisions/`, `knowledge/`). `composer create-project` refuses non-empty targets, so installation must go through a temp directory. PHP 8.5.2, Composer 2.9.2, Node 25 available locally.

## Goals / Non-Goals

**Goals:**
- Runnable Laravel skeleton at repo root with all infra files intact.
- The five Quality Commands from `CLAUDE.md` green locally and enforced in CI.
- Authenticated Filament operator panel shell (`/admin`).
- End-to-end smoke test of the ralph loop on real tasks.

**Non-Goals:**
- ERP domain logic, `app/Modules/` skeletons, RBAC beyond a single seeded operator, production DB engine choice, frontend storefront, deployment.

## Decisions

- **Install procedure (CRITICAL — task 1.1):** `composer create-project laravel/laravel:^13.0 /tmp/newco-laravel-skeleton --no-interaction`, then copy into the repo root with `rsync -a --ignore-existing --exclude .git /tmp/newco-laravel-skeleton/ ./` so that **no existing file is ever overwritten** (Laravel's `README.md` and `.gitignore` lose to ours). Then merge Laravel's `.gitignore` entries into the existing root `.gitignore` manually (union, keep our comments), run `composer install`, `php artisan key:generate`. Verify with `git status` that no pre-existing file shows as modified/deleted before proceeding.
- **Versions (pinned per ADR 2026-06-11-stack-versions-and-filament-ai-tooling):** Laravel **13.x** (`laravel/framework:^13.0`) and Filament **5.x** (`filament/filament:^5.0`). Record the exact installed versions (PHP, Laravel, Filament, Pest, PHPStan/Larastan, Pint, Boost) in `docs/development.md` and in `progress.md`; `composer.lock` freezes them from install onward. Any major upgrade is a future deliberate change.
- **Database:** SQLite for dev (`database/database.sqlite`), `:memory:` for tests. The production engine ADR is explicitly out of scope (open gate).
- **Operator seeding:** `database/seeders/OperatorSeeder.php` reading name/email/password from env vars documented in `.env.example`. No secrets in git (CLAUDE.md invariant; `secrets-management` discipline).
- **Filament:** `composer require filament/filament:"^5.0"`; single panel, id `admin`, path `/admin`, default `AdminPanelProvider`. Install with `--no-interaction` flags. No resources/widgets beyond the default dashboard.
- **AI tooling (per Filament 5.x AI guidance, ADR 2026-06-11-stack-versions-and-filament-ai-tooling):** `composer require laravel/boost --dev` + `php artisan boost:install`, selecting the Laravel and Filament guidelines. Boost output goes to `AGENTS.md`/its own guideline files — it must NOT modify the protected `CLAUDE.md` (verify with `git status`, same discipline as task 1.1). Reference https://filamentphp.com/docs/llms.txt in `docs/development.md` as the agent-facing Filament docs index. Filament Blueprint (premium) is explicitly out of scope.
- **Static analysis:** Larastan via `phpstan.neon`, highest level that passes on the fresh skeleton without a baseline (aim level 8+; do not commit a baseline file for skeleton code).
- **CI:** single GitHub Actions job on `push` + `pull_request`: checkout → PHP setup (match local minor version) → composer cache + install → `vendor/bin/pint --test` → `vendor/bin/phpstan analyse` → `php artisan test` (SQLite). No deploy steps.
- **Composer script ergonomics:** add `composer format` / `lint` / `analyse` / `test` aliases; the `CLAUDE.md` Quality Commands table remains the authority and is not modified.

## Risks / Trade-offs

- **rsync merge risk:** `--ignore-existing` protects our files but can silently skip a Laravel file we accidentally created earlier — mitigated by the `git status` verification step inside task 1.1.
- **Version drift vs these docs:** majors are pinned (`^13.0` / `^5.0`); exact minor/patch recorded at install time in `docs/development.md` + `progress.md`, frozen by `composer.lock`.
- **Filament installer prompts:** mitigated with `--no-interaction`; if the installer still requires input, the task notes say to fall back to manual provider publication steps from the Filament docs.
- **`boost:install` prompts:** the installer is interactive (agent/guideline selection); if non-interactive flags are unavailable, follow the manual installation steps from the Boost/Filament docs and verify afterwards that only `AGENTS.md`/Boost files were created — never the protected `CLAUDE.md`.
- **CI PHP version mismatch** (local 8.5 vs runner): pin the workflow to the closest available runner version ≥ 8.4 and note it in `docs/development.md`.
