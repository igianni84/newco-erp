---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 14:58 (ralph iter — task 6.2 done, committing green).** Docs-only task (NO code). Recorded the now-built operator-auth foundation across three docs. (1) `CONTEXT.md` *Actor context*: rewrote to the wired seam (authenticated operator → `newco_ops`/`Operator.id`; `system` for console/queue/unauth + the not-yet-wired customer/producer guards; run-as override beats both) and **removed** the "reads NO authentication state … until that ADR wires it" clause; kept the test-pinned `**Actor context**` header + the exact `_Avoid_: session, current user, auth guard` line verbatim. (2) `docs/development.md`: fixed the **stale** "standalone / not wired into `DatabaseSeeder`" claim (5.2 wired it) → now documents `php artisan db:seed` = `DatabaseSeeder`→`RoleSeeder` then `OperatorSeeder` (order matters), the `OPERATOR_NAME/EMAIL/PASSWORD` env contract, + a new **"Operator authentication & RBAC"** subsection. (3) `decisions/INDEX.md`: added an _Identity/auth — **built** 2026-06-15_ marker (commit `059f5e1` had dropped it from the open line when *decided*). Also reworded one now-false test comment in `FoundationsDocsTest.php:56` (comment-only).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission **8.0.0** · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- Branch `ralph/operator-auth-foundation`: full suite **359/359 SQLite** (FoundationsDocsTest CONTEXT.md pins + DevelopmentDocsTest docs/development.md pins both green). phpstan **0 @ max**, pint + pint --test clean, `openspec validate --strict` valid. **PG17 NOT run this iter — docs-only, no DB/schema/SQL** (cross-engine close is 6.3).
- Commit pending this iter: **6.2**. 1.1→6.2 done (**11 of 12**); **6.3 next (FINAL)**.

## Active Change & Next Task
- **ACTIVE: `operator-auth-foundation`** — 12 tasks; **11 done, 1 remains, no blocker.**
- **Next: 6.3 Full cross-engine close (verification task — NO product code).** Run the ENTIRE Pest suite on SQLite **and** PostgreSQL 17 (print `DRIVER=pgsql`, remove the container per the Codebase-Patterns recipe); `phpstan analyse` 0 @ max; `pint --test` clean. **Record the PG17 run + the pinned spatie version (8.0.0) in `progress.md`** (acceptance). Touches only `progress.md`/`tasks.md`/`hot.md`/`log.md`. After 6.3 green → reply exactly `<promise>CHANGE_COMPLETE</promise>` (do NOT archive/merge — the human does that after review). Safe to relaunch `./ralph.sh`.

## Blockers & Decisions Needed
- **None.** All hard acceptance bullets for 6.2 met (CONTEXT.md seam, docs/development.md spatie+wiring+env vars, INDEX built-marker, validate strict, lint/format).
- Standing founder decisions: 2FA opt-in INCLUDED (enforcement→security review); User→Operator replacement done; bootstrap operator holds all 3 roles.
- **Open ADR gates (don't step in):** queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack/SPA/Sanctum (Module S) · authority-tier RBAC (`feedback_prd_rr_approval`) · SoD floor + lifecycle FSM (`catalog-lifecycle-approval`) · MFA enforcement (security review).

## Open Patterns
- **Doc-pinning tests share a `developerDoc()` reader** (defined in `EventSubstrateDocsTest.php`; used by `FoundationsDocsTest`/`ModuleTemplateDocsTest`) → validate docs changes with the **full suite**, NOT a single-file `--filter`/path subset (excluding the helper-defining file reds with `Call to undefined function developerDoc()` — an isolation artifact, not a real fail). `DevelopmentDocsTest` is self-contained. Pins are byte-exact `toContain` — keep tokens char-for-char; reword the doc-change's own stale comments same pass.
- **Cross-engine close recipe (for 6.3):** `docker run -d --name pg … postgres:17`, busy-poll `pg_isready` (foreground `sleep` blocked), `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 … php artisan test`, `docker rm -f pg`. Confirm live `DRIVER=pgsql`. **log.md:** append ONLY via `scripts/memlog.sh`; hot.md ≤550 words.
- **Laravel deep-merges the framework base `config/auth.php` UNDER the app's** (`guards`/`providers`/`passwords`) — inert `web`/`users` linger; `defaults` IS fully overridden (default guard = `operator`). `actingAs($model)` no guard-arg uses `config('auth.defaults.guard')` = `operator`. Auth-principal models exempt from the module-table-prefix arch test (D7; ADR `2026-06-15-auth-principal-table-naming`). Full rules: `knowledge/laravel/rules.md`.
