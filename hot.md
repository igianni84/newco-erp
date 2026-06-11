---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-11
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-11 12:58 (ralph iteration — task 3.2 green)** — **Laravel Boost v2.4.10 installed** (`--dev`). `boost:install --guidelines --no-interaction` driven deterministically: committed `boost.json` pre-seeds `agents: [claude_code]` + `packages: [filament/filament]` (Boost reads defaults from it, skips detection); committed `config/boost.php` redirects `agents.claude_code.guidelines_path` **CLAUDE.md → AGENTS.md** (vendor default would APPEND to the protected `CLAUDE.md` — never remove this override). `AGENTS.md` (generated, 15KB, single `<laravel-boost-guidelines>` block) = 9 guidelines incl. **laravel/core + filament/filament + enforce-tests**. MCP/skills NOT installed (no `.mcp.json`, nothing under `.claude/`). New `AiToolingTest` (5 pins incl. the executable guidelines_path guard). Zero protected-file drift verified.

## Build & Quality Status
- **Version snapshot:** PHP **8.5.2** · Composer **2.9.2** · Laravel **13.15.0** · Filament **v5.6.7** + Livewire **v4.3.1** · **Boost v2.4.10** · Pest **4.7.2** (+plugin-laravel 4.1.0) · PHPStan **2.2.2** · Larastan **3.10.0** · Pint **1.29.1**. SQLite dev DB; tests on sqlite `:memory:`.
- Quality loop (last run, 12:58): format ✅ · test_filter ✅ · full test **25/25 (59 assertions)** ✅ · type_check **0 errors @ level max** ✅ · lint ✅ · `openspec validate --strict` ✅. `QualityToolingTest` pins survived the Boost composer rewrite.
- `phpstan.neon`: level max, `stubFiles: vendor/filament/forms/.stubs.php`, `phpstan-bootstrap.php` ini_set 1G (Homebrew 128M OOM fix) — all unchanged.
- Quality-command output is shell-wrapped JSON (`{"tool":"pest","result":"passed",...}`), not vanilla CLI output.
- CI: not configured yet (task 3.3 — next).

## Active Change & Next Task
- `openspec/changes/bootstrap-laravel-app/` — APPROVED, strict-valid, **8/10 tasks done** (1.1–3.2 ✅).
- **Next task: 3.3** — GitHub Actions `.github/workflows/ci.yml`: triggers push + pull_request; PHP setup matching local minor (**8.5**); composer cache; steps `vendor/bin/pint --test` → `vendor/bin/phpstan analyse` → `php artisan test` on SQLite. Quality loop green locally before committing the workflow. Notes: phpstan memory handled by committed `phpstan-bootstrap.php` (no flag needed); Boost is dev-only, needs no CI steps; suite baseline 25/59.
- Then: 3.4 docs (`docs/development.md` + INDEX row; include Boost v2.4.10 in version table, https://filamentphp.com/docs/llms.txt as agent-facing Filament docs index, `boost:install --guidelines -n` regeneration command) — final task re-runs everything.
- Branch: `ralph/bootstrap-laravel-app`. Pinned per ADR 2026-06-11: laravel ^13.0, filament ^5.0, boost --dev.

## Blockers & Decisions Needed
- None active for the bootstrap change.
- Open ADR gates (none block bootstrap): production DB engine, identity/auth (owns `User::canAccessPanel()`), queue driver, event substrate, audit store, object storage, EU hosting, frontend stack.
- External sandbox credentials (Airwallex/Xero/HubSpot) needed before F6 changes — human-procured.

## Open Patterns
- **Boost contract:** `AGENTS.md` is generated — never hand-edit; regenerate via `php artisan boost:install --guidelines --no-interaction` (re-runs replace the block in place). `boost.json` + `config/boost.php` make it deterministic and CLAUDE.md-safe; `AiToolingTest` fails if the redirect is removed.
- **3.3 heads-up:** CI = pint --test → phpstan analyse → artisan test, PHP 8.5, composer cache; SQLite needs no service container; `github-actions-templates` skill available.
- **Filament 5 FQCNs:** auth pages at `Filament\Auth\Pages\*`; failed login = ValidationException on `data.email`; routes `filament.admin.pages.dashboard` / `filament.admin.auth.login`.
- **Panel testing idiom:** `Livewire::test(Login::class)->fillForm([...])->call('authenticate')`; Pest `toThrow(X::class, 'substring')` = contains-check, the level-max-clean form.
- Versions from `composer show <pkg>` flat list; Pest `--filter` is description-regex; `php artisan test <file>` runs one file.
- Full list: `openspec/changes/bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
