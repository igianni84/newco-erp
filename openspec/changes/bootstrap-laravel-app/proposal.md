## Why

The repository has its development infrastructure (spec, OpenSpec, ralph loop, memory systems) but zero application code. Every future module change needs a runnable Laravel skeleton with the five Quality Commands from `CLAUDE.md` actually wired and enforced in CI. This change is also the deliberate **smoke test of the ralph loop itself**: small, low-risk, exercising every part of the machine (task selection, quality loop, circuit breaker paths, memory updates, stop tokens) before real module work begins.

## What Changes

- Install Laravel (latest stable) at the repo root via a temp-dir merge that preserves all existing infrastructure files.
- Wire the quality pipeline: Pint (format/lint), Pest (tests), Larastan (static analysis), matching the `CLAUDE.md` Quality Commands table exactly.
- Install Filament and expose an authenticated operator panel shell at `/admin` (no resources yet).
- Add GitHub Actions CI that fails on any failing test, static-analysis error, or lint violation.
- Verify the health endpoint (`GET /up`) with a feature test.
- Write `docs/development.md` (setup + quality commands + loop usage quickstart).

## Capabilities

### New Capabilities
- `platform`: application skeleton and cross-cutting platform behavior — health endpoint, operator panel shell, quality pipeline & CI.

### Modified Capabilities
<!-- none — this is the first change; openspec/specs/ is empty -->

## Impact

- **New:** `composer.json`/`composer.lock`, `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `tests/`, `artisan`, `package.json`, `vite.config.js`, `phpstan.neon`, `pint.json` (or framework defaults), `.github/workflows/ci.yml`, `docs/development.md`.
- **Merged (never replaced):** root `.gitignore` (union with Laravel's), existing `README.md`/`CLAUDE.md`/infra files remain untouched.
- **Deliberately NOT in this change:** any ERP domain logic, module skeletons (`app/Modules/`), production DB engine choice (SQLite only), auth/identity decision (panel uses the default local users table for the seeded operator), consumer-facing surfaces. Module scaffolding arrives with the first Phase-1/Module-0 change after its ADR gates clear.
