---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-11
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-11 13:05 (ralph iteration — task 3.3 green)** — **GitHub Actions CI committed**: `.github/workflows/ci.yml`, single `quality` job on ubuntu-latest; bare `push:` + `pull_request:` triggers (every push/PR per the platform delta spec); `setup-php@v2` **php 8.5** (local minor) + explicit extensions **incl. `pdo_sqlite`/`sqlite3`** (no package declares them — runtime-only need); `actions/cache@v4` keyed on `composer.lock`; **`cp .env.example .env && php artisan key:generate` before tests** (skeleton `phpunit.xml` ships NO `APP_KEY` — bare checkout would throw `MissingAppKeyException` on encrypter-touching panel tests); gates in CLAUDE.md order `pint --test` → `phpstan analyse` → `php artisan test` (in-memory SQLite via phpunit.xml, no service container, no memory flag — committed `phpstan-bootstrap.php` covers it). New `CiWorkflowTest` (5 pins on the **`run:`-prefixed** lines). CI has not yet run remotely — first run when Giovanni pushes.

## Build & Quality Status
- **Version snapshot:** PHP **8.5.2** · Composer **2.9.2** · Laravel **13.15.0** · Filament **v5.6.7** + Livewire **v4.3.1** · **Boost v2.4.10** · Pest **4.7.2** (+plugin-laravel 4.1.0) · PHPStan **2.2.2** · Larastan **3.10.0** · Pint **1.29.1**. SQLite dev DB; tests on sqlite `:memory:`.
- Quality loop (last run, 13:05): format ✅ · test_filter ✅ · full test **30/30 (70 assertions)** ✅ · type_check **0 errors @ level max** ✅ · lint ✅ · `openspec validate --strict` ✅.
- CI: workflow committed (3.3), awaiting first remote run on push. Local quality loop remains the authority until then.

## Active Change & Next Task
- `openspec/changes/bootstrap-laravel-app/` — APPROVED, strict-valid, **9/10 tasks done** (1.1–3.3 ✅).
- **Next task: 3.4 (FINAL)** — `docs/development.md`: clone → composer install → env → migrate → serve; the five Quality Commands; how to run/monitor `./ralph.sh`; exact installed versions **incl. Boost v2.4.10** (snapshot above); link https://filamentphp.com/docs/llms.txt as agent-facing Filament docs index; CI PHP-pin note (8.5, bump with local minor, floor ≥ 8.4) per design risk bullet; `boost:install --guidelines -n` as AGENTS.md regeneration command. Update `docs/INDEX.md` row. Re-run ALL quality commands one final time. **Then all 10 tasks are done → final pass over every acceptance bullet → emit `CHANGE_COMPLETE`** (no archive, no merge — human's job).
- Branch: `ralph/bootstrap-laravel-app`. Pinned per ADR 2026-06-11: laravel ^13.0, filament ^5.0, boost --dev.

## Blockers & Decisions Needed
- None active for the bootstrap change.
- Open ADR gates (none block bootstrap): production DB engine, identity/auth (owns `User::canAccessPanel()`), queue driver, event substrate, audit store, object storage, EU hosting, frontend stack.
- External sandbox credentials (Airwallex/Xero/HubSpot) needed before F6 changes — human-procured.

## Open Patterns
- **NEW lesson (lessons.md):** git-guardrails' unanchored `(rm|mv)…spec` regex matches prose like "platform spec" in heredocs — write memory files (progress/log/hot/lessons) via Edit/Write tools, never Bash heredocs. Suggested hook fix for Giovanni: anchor the verb `(^|[;&|[:space:]])(rm|mv)[[:space:]]`.
- **Pin artifacts by executable form** (`run: <cmd>`), never bare command strings — comments mentioning commands satisfy `strpos`/`toContain` first (bit 3.3 once).
- **Boost contract:** `AGENTS.md` generated — regenerate via `php artisan boost:install --guidelines -n`; `boost.json` + `config/boost.php` keep it deterministic and CLAUDE.md-safe; `AiToolingTest` guards the redirect.
- **Filament 5 FQCNs:** auth pages `Filament\Auth\Pages\*`; failed login = ValidationException on `data.email`; `Livewire::test(Login::class)->fillForm([...])->call('authenticate')` idiom.
- Versions from `composer show <pkg>` flat list; Pest `--filter` is description-regex; `php artisan test <file>` runs one file.
- Full list: `openspec/changes/bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
