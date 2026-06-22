---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-kyc-sanctions` — ralph loop RUNNING; task 4.2 done, 11/13; GROUP 4 COMPLETE).** Ran the GUIDE §2.7 PostgreSQL 17 ritual — verify-only, no production/test/spec change. `postgres:17` on `-p 55432:5432` (image cached), polled `pg_isready` INSIDE the container (host `sleep` is sandbox-blocked → loop via `docker exec pg bash -c 'for … sleep 1 … done'`; ready 3s), ran the scoped suite `tests/Feature/Modules/OperatorPanel/Parties` + `…/Catalog/ProductMasterConsoleI18nTest.php` (declares the shared `scanOperatorConsoleHardcodedSinks` helper a bare Parties path would skip), then `docker rm -f pg`. **GREEN: 372/372, 1854 assn, exit 0, 43.1s** — all 25 Parties console files incl. this slice's two new ones pass on the real prod engine, confirming the bigint-as-string envelope idioms (loose `actor_id` `toEqual`, `(string)` `entity_id`) and `DatabaseMigrations`-committed in-tx event appends are engine-portable.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN on SQLite (4.1 iteration): full suite 1495/1495 (8263 assn, exit 0).** **This iteration (4.2): PG17 scoped run 372/372 (1854 assn, exit 0).** No file changed this iteration except the memory trio + the 4.2 checkbox flip — verify-only. PHPStan/Pint/full-pest NOT re-run (that's task 5.1's dedicated scope — one task per iteration).
- Full suite OOMs at PHP default 128 MB in result parsing — run pest with `php -d memory_limit=-1`.

## Active Change & Next Task
- **`operator-console-parties-kyc-sanctions` — APPROVED, IN PROGRESS (11/13).** Delta on `operator-console`: 2 ADDED (KYC; sanctions) + 2 MODIFIED Customer reqs; 13 tasks / 5 groups. Groups 1+2 (KYC) + 3 (sanctions) + 4 (PG17 closing-chain, both halves) DONE.
- **Next task 5.1 (quality gates):** `vendor/bin/pint --test` clean; `vendor/bin/phpstan analyse` at max green — incl. `NoEloquentWriteInOperatorPanelRule` (writes route only through the four domain Actions) and `ModuleBoundariesTest` **UNCHANGED** (the `Parties\Enums` operand-enum import rides the existing carve-out; `KycStatus` never imported); `php -d memory_limit=-1 vendor/bin/pest` (full) green. Acceptance: all four commands pass; `git diff --name-only main | grep -E '^(spec/|openspec/specs/|tests/Architecture/)'` → NONE, no composer dep, no migration.
- Then 5.2 (`openspec validate … --strict` + memory consolidation). After all 13: review/merge → semantic-verify (GUIDE §2.7) → `openspec archive`.

## Blockers & Decisions Needed
- **None blocking task 5.1.** No open-ADR gate crossed. 5.1 is pure quality-command verification + diff-shape check (no Docker needed).
- **Holds push gate STILL pending** (separate change) — origin/main push of the Holds archive+merge commits + `git branch -d ralph/operator-console-parties-holds` await Giovanni's go (classifier-gated). Local merge+archive already done.

## Open Patterns
- **PG17 ritual under this harness:** host `sleep` is blocked → put the `pg_isready` poll loop INSIDE the container (`docker exec pg bash -c 'for … sleep 1 … done'`); the host shell waits on one foreground `docker exec`. Port 55432 dodges the local postgres:16 on 5432. SQLite-green is necessary but not sufficient (`knowledge/testing/rules.md`) — PG17 re-run confirms the bigint envelope idioms.
- **Cross-slice closing-chain through a SHIPPED widget (D11)** + **kyc-sanctions enum discipline** (`KycStatus` STATE enum cast-only, never imported; `SanctionsStatus`/`ScreeningTriggerSource` OPERAND enums imported via carve-out; KYC verbs event-silent per D7, sanctions screening EMITS): both consolidated in this change's `progress.md ## Codebase Patterns`.
