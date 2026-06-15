# Development Guide

How to set up, run, and quality-check the NewCo ERP Laravel application, and how to drive the autonomous ralph loop. Version snapshot taken 2026-06-11 by the `bootstrap-laravel-app` change; `tests/Feature/DevelopmentDocsTest.php` cross-checks the version table below against `composer.lock`, so this page fails the suite if it drifts.

For the *what* and *why* of the repo (spec authority, OpenSpec lifecycle, memory systems), start at the root [`README.md`](../README.md) and [`CLAUDE.md`](../CLAUDE.md); the operator's playbook is [`GUIDE.md`](../GUIDE.md) (Italian).

To build inside a module, follow [`module-template.md`](module-template.md) — the conventions (canonical layout, boundary law, provider / operator-surface / persistence / test patterns, naming cascade) every F2+ module change inherits.

## Prerequisites

- **PHP ≥ 8.5** with `pdo_sqlite`/`sqlite3` (snapshot: 8.5.2) — the floor is enforced by `tests/Feature/PlatformRequirementsTest.php`
- **Composer 2.x** (snapshot: 2.9.2)
- **Node ≥ 20** with npm — only for Vite assets and `composer dev`
- For the ralph loop: **claude CLI**, **jq**, **openspec** (`npm install -g @fission-ai/openspec@latest`; `ralph.sh` falls back to `npx` if missing)

## Setup (clone → install → env → migrate → serve)

```bash
git clone https://github.com/igianni84/newco-erp.git
cd newco-erp

composer install                      # PHP dependencies (composer.lock is authoritative)
cp .env.example .env                  # SQLite config is the committed default
php artisan key:generate

touch database/database.sqlite        # dev DB file (gitignored)
php artisan migrate

php artisan serve                     # http://127.0.0.1:8000
```

Smoke checks: `GET /up` returns 200 (health endpoint), `GET /admin` redirects to the operator login.

Alternatives: `composer setup` is the skeleton's one-shot equivalent (install → env → key → migrate → npm install/build; requires Node). `composer dev` runs the full dev stack (server + queue worker + log tail + Vite) via `npx concurrently`.

### Operator panel account

The Filament panel at `/admin` has no self-registration. Seed the single operator from env vars (documented in `.env.example`, never committed with real values):

```bash
# in .env: OPERATOR_NAME, OPERATOR_EMAIL, OPERATOR_PASSWORD (all required)
php artisan db:seed --class=OperatorSeeder
```

The seeder is standalone (deliberately not wired into `DatabaseSeeder`), idempotent (`updateOrCreate` keyed on email), and throws a `RuntimeException` naming any missing variable — an empty `OPERATOR_PASSWORD` is the usual culprit.

## Quality Commands

The authority is the `CLAUDE.md` Quality Commands table — reproduced verbatim:

| Command | Purpose | Value |
|---|---|---|
| format | Code formatter | `vendor/bin/pint` |
| test_filter | Run specific test | `php artisan test --filter={name}` |
| test | Run tests | `php artisan test` |
| type_check | Static analysis | `vendor/bin/phpstan analyse` |
| lint | Linter (check only) | `vendor/bin/pint --test` |

Composer aliases (pinned by `tests/Feature/QualityToolingTest.php`): `composer format` · `composer lint` · `composer analyse` · `composer test`.

Tool specifics:

- **Pint** uses the `laravel` preset, made explicit in `pint.json` — the documented home for any future rule additions.
- **PHPStan/Larastan** runs at **`level: max` with no baseline** (`phpstan.neon`, paths `app`/`database`/`routes`/`tests`). Suppression is banned — narrow `mixed`, fix root types. The committed `phpstan-bootstrap.php` raises a finite CLI `memory_limit` to 1G (Larastan reflects the whole framework; some hosts default to 128M), so the bare command needs no `--memory-limit` flag anywhere. Filament's Livewire test macros are typed via the vendor-shipped stub (`parameters.stubFiles`).
- **Pest** is the test runner (`php artisan test` auto-delegates). Tests run on in-memory SQLite per `phpunit.xml`. `--filter` matches the test *description* as a regex — quote it and avoid metacharacters: `php artisan test --filter='responds healthy on /up'`. To run one file: `php artisan test tests/Feature/HealthCheckTest.php`.

## Continuous Integration

`.github/workflows/ci.yml` runs on **every push and pull request**, in two lanes (`foundations-domain-events-audit` design D8):

- **`quality`** — composer cache + install, `cp .env.example .env && php artisan key:generate` (the skeleton `phpunit.xml` ships no `APP_KEY`), then the gates in Quality Commands order: `vendor/bin/pint --test` → `vendor/bin/phpstan analyse` → `php artisan test` on **in-memory SQLite** (the dev/test engine, no service container). Lint and PHPStan are engine-independent, so they run **only here, once**.
- **`tests-pgsql`** — the same `php artisan test` re-run against a **PostgreSQL 17** service container. 17 is the production-DB ADR floor (`decisions/2026-06-12-production-db-engine.md`); CI tests the floor, managed providers may run newer. This lane proves the Postgres-truthful migration branches the SQLite lane can't exercise — the `actor_role` CHECK constraint, the partial `WHERE status = 'pending'` index, and the plpgsql immutability trigger functions. The connection is passed as job-level env (`DB_CONNECTION: pgsql` + host/port/database/username/password); because `phpunit.xml`'s `<env>` entries are not `force="true"`, they do **not** clobber those already-set real env vars, so `php artisan test` runs on Postgres with no `--env` gymnastics.

The contract is pinned by `tests/Feature/CiWorkflowTest.php` (both job names, the `postgres:17` image, the `DB_CONNECTION: pgsql` switch, and that the lint/type_check gates appear exactly once). The ralph loop never pushes, so the `tests-pgsql` lane's **first live run is the human push after review** — treat that run as part of the change's acceptance; if it reds, fix it in a follow-up iteration before merge.

**CI PHP pin:** both lanes pin `php-version: '8.5'` — the local minor at bootstrap time, satisfying the project floor of **PHP ≥ 8.5**. When the local PHP minor is upgraded, bump the workflow pin alongside it (and this page's snapshot); never drop below 8.5. The `tests-pgsql` lane additionally adds the `pdo_pgsql`/`pgsql` extensions to the setup-php list.

## The ralph loop (autonomous implementation)

`./ralph.sh` drives the OpenSpec work state machine: each iteration launches a **fresh** Claude Code instance that implements exactly ONE unchecked task from the active change's `tasks.md`, runs the quality loop, commits, and persists memory. State between iterations lives only in files + git.

```bash
./ralph.sh [--change <name>] [--force] [max_iterations]
```

- `--change <name>` — run a specific change from `openspec/changes/<name>/`. Default: the alphabetically-first change with an `APPROVED` marker and unchecked tasks.
- `--force` — skip the `APPROVED` gate (smoke tests only).
- `max_iterations` — default 10.
- Work lands on the `ralph/<change>` branch (created/checked out automatically). Start with a clean working tree.

Environment variables:

- `RALPH_MODEL` — model per iteration (default: `claude-opus-4-8[1m]` — Opus 4.8, 1M context). Override per run, e.g. `RALPH_MODEL=claude-sonnet-4-6 ./ralph.sh`.
- `RALPH_EFFORT` — reasoning effort per iteration: `low|medium|high|xhigh|max` (default: `max`).
- `CLAUDE_FLAGS` — extra flags appended to the `claude` invocation, *after* the `--model`/`--effort` pins above; the later flag wins, so this still overrides the model, e.g. `CLAUDE_FLAGS="--model opus" ./ralph.sh`.

### Monitoring a run

- **Live:** each iteration's full agent output streams to the terminal; the loop prints per-iteration progress (`n/total tasks done`) and stall warnings.
- **Tasks:** checkboxes in `openspec/changes/<change>/tasks.md` flip as tasks complete; failures leave `> ⚠ FAILED` notes under the task.
- **Narrative:** `openspec/changes/<change>/progress.md` — one entry per iteration plus consolidated `## Codebase Patterns` at the top.
- **Last iteration tail:** `openspec/changes/<change>/.last-output` (kept on failure paths, removed on completion).
- **Repo state:** `hot.md` (state cache) and `log.md` (append-only ledger) at the root; `git log --oneline main..ralph/<change>` for the commit trail.

Exit codes: `0` change complete · `1` max iterations reached · `2` preflight error · `3` agent requested human help (see `.last-output` + `progress.md`) · `4` stalled (3 iterations without progress) · `5` integrity violation (a protected layer was modified — the loop never touches `spec/`, `openspec/specs/`, `CLAUDE.md`, `RALPH.md`, `ralph.sh`, `.claude/`).

The loop never pushes; humans push. On `CHANGE_COMPLETE` follow the closure ritual in `GUIDE.md` §2.7: review the branch, merge `--no-ff`, run the semantic check, then `openspec archive <change> --yes`.

## AI tooling (Boost guidelines & agent-facing docs)

- **`AGENTS.md`** (root) is **generated** by Laravel Boost — never hand-edit. Regenerate with `php artisan boost:install --guidelines --no-interaction`; re-runs replace the `<laravel-boost-guidelines>` block in place.
- The committed **`boost.json`** (agents: `claude_code`; packages: `filament/filament`) makes that command fully deterministic, and **`config/boost.php`** redirects the Claude Code guideline output to `AGENTS.md` — Boost's vendor default would append to the protected `CLAUDE.md`. `tests/Feature/AiToolingTest.php` guards both.
- **Agent-facing Filament docs index: <https://filamentphp.com/docs/llms.txt>** — the entry point AI agents should use for Filament 5.x documentation lookups.
- `laravel/boost` is a dev-only dependency; it installs no MCP server (`.mcp.json` is absent), but `.claude/` carries the loop's hooks, skills, and team memory.

## Installed versions (exact, snapshot 2026-06-11)

Majors are pinned per ADR `decisions/2026-06-11-stack-versions-and-filament-ai-tooling.md`; `composer.lock` freezes the resolved versions. After any `composer update`, refresh this table — `DevelopmentDocsTest` fails if it no longer matches `composer.lock`.

| Tool | Package | Version | Constraint |
|---|---|---|---|
| PHP | — | 8.5.2 | ≥ 8.5 (project floor) |
| Composer | — | 2.9.2 | 2.x |
| Laravel | `laravel/framework` | 13.15.0 | `^13.8` |
| Filament | `filament/filament` | 5.6.7 | `^5.0` |
| Livewire | `livewire/livewire` | 4.3.1 | (transitive) |
| Pennant | `laravel/pennant` | 1.23.0 | `^1.23` |
| Spatie Permission | `spatie/laravel-permission` | 8.0.0 | `^8.0` |
| Laravel Boost (dev) | `laravel/boost` | 2.4.10 | `^2.4` |
| Pest (dev) | `pestphp/pest` | 4.7.2 | `^4.7` |
| Pest Laravel plugin (dev) | `pestphp/pest-plugin-laravel` | 4.1.0 | `^4.1` |
| PHPStan (dev) | `phpstan/phpstan` | 2.2.2 | (via Larastan) |
| Larastan (dev) | `larastan/larastan` | 3.10.0 | `^3.10` |
| Pint (dev) | `laravel/pint` | 1.29.1 | `^1.27` |

`laravel/pennant` was added 2026-06-13 by the `foundations-money-i18n-flags` change as the feature-flag infrastructure (design D5; gates the NFT/on-chain EXT-1 surfaces, off at launch). It is a first-party Laravel package adopted at its latest stable, so per the stack ADR's framework-conventional posture it needs **no standalone dependency ADR**. Its published `features` backing table migration (`database/migrations/*_create_features_table.php`) is Postgres-truthful / SQLite-compatible.

`spatie/laravel-permission` was added 2026-06-15 by the `operator-auth-foundation` change as the **operator RBAC mechanism** (design D4) — DB-backed, runtime-configurable, guard-aware roles/permissions. The dependency is authorised by `decisions/2026-06-15-identity-auth.md`, which is the required "no new heavyweight dependency without an ADR" ADR; the major is pinned (`^8.0`, resolved 8.0.0). Its config (`config/permission.php`, **teams off** — operator-scoping keys on `guard_name`, never a team column) and its `create_permission_tables` migration are published; the migration is Postgres-truthful / SQLite-compatible (verified `migrate:fresh` clean on PostgreSQL 17). That one published migration is **excluded from PHPStan** in `phpstan.neon` (it resolves its table/column names through the `mixed`-typed `config()` helper, which `level: max` flags) — third-party code we publish verbatim and never hand-edit, scoped to the single file so our own migrations stay fully analysed.
