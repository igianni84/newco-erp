---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (§2.7 close ritual of `operator-console-parties-producer` — MERGED + ARCHIVED).** First Parties/Module-K console (the Producer surface: create + status FSM activate[KYC-gated]/retire[Club-sunset cascade] + 4-verb audit-only KYC FSM; non-catalog trait-reuse, ADR 2026-06-20). Reviewed → merged `--no-ff` to `main` (`69fec90`) → semantic-checked → archived (`c938364`): `openspec archive` merged its 3 ADDED requirements into the living `openspec/specs/operator-console/spec.md` (validates). Semantic: 0 CRITICAL, 1 WARNING + 2 SUGGESTION accepted. **Not pushed** — `main` is +9 vs `origin/main`; humans push.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **`main` GREEN post-merge: full suite 1206/1206 SQLite (6850 assn), phpstan 0, pint clean, `openspec validate operator-console --strict` OK.** PG17 gate was passed in-loop (closing test `978f06f`); not re-run for the merge — the change is additive and `main` was a clean ancestor of the branch.
- **Run-cmd gotchas:** full suite OOMs under bare `php artisan test` (128M) → use `php -d memory_limit=-1 vendor/bin/pest` / `… phpstan analyse`. A test reusing a sibling file's top-level helper (e.g. `scanOperatorConsoleHardcodedSinks`) must be run via `--filter`/full suite, NOT a file/dir path (a Parties-only path omits the Catalog declaring file → `function_exists` guard false-reds; for a folder-wide PG17 run APPEND `tests/Feature/Modules/OperatorPanel/Catalog/ProductMasterConsoleI18nTest.php`). PG17: docker `postgres:17` container `newco-pg17-test`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **No active change** (`openspec list` empty). The Producer console is merged + archived.
- **Next change:** `operator-console-parties-supply-side` (Club + ProducerAgreement consoles) — reuses the proven non-catalog trait-reuse recipe (ADR 2026-06-20), consolidated in the archived change's progress Codebase Patterns. Run `/spec-to-change`, or pick it up if already APPROVED.

## Blockers & Decisions Needed
- **Push pending — humans push.** `main` +9 vs `origin/main` (6 feature + approve + merge + archive commits). Branch `ralph/operator-console-parties-producer` not deleted (`git branch -d` is the human's call).
- Semantic-check findings (non-blocking, candidates for the supply-side change or `knowledge/testing`): **W1** R1 "no Supplier created" has no console-test assertion (console can't import/create a Supplier — it's a domain guarantee). **S1** activation-blocked tested via `pending`, not `rejected`. **S2** waive tested from pending/rejected/verified, not NULL (NULL is a legal from-state, confirmed). All are OR-branches on a state-agnostic console path.
- Cleanup: `newco-pg17-test` docker container may still be running (`docker rm -f newco-pg17-test` to drop).

## Open Patterns
- **The non-catalog Parties console pattern is PROVEN and now in the living spec.** Recipe — Resource (own status/kyc columns, no `lifecycleStateColumn`), View page (`SurfacesDomainActions` trait, form-less verbs, dual FSM on one page), create write-through, i18n kit-key completeness guard, closing-chain test (page-driven `it()`, emergent-set `toEqualCanonicalizing`, set-wide envelope `foreach`, loose `toEqual` for uncast bigints). Club/Agreement/Customer consoles reuse it.
