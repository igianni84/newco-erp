---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — `reconcile-hold-registry-eight-types` ralph iter 5: task 3.1 DONE → ALL 5/5 TASKS `[x]` → `<promise>CHANGE_COMPLETE</promise>` emitted.** 3.1 was verify/doc-only: recorded the Protected-file (`CLAUDE.md`/`CONTEXT.md`) still-"six/eight" Hold-terminology hand-off into `progress.md` for Giovanni's manual edit. **The change's diff touches NO Protected file** — both stay un-staged `M`. Final gates green THIS iter: Hold suite **86/86** (571 assertions, ~3s, SQLite), `openspec validate reconcile-hold-registry-eight-types --strict` → valid exit 0. ZERO code/test/spec/migration/Protected-file touched across the whole change (spec-only reconciliation, design L1).

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **No code/test/spec/PHP touched in ANY task of this change** → format/test/type_check/lint N/A. Full suite 1951/1951 UNCHANGED from HEAD (spec-doc-only change). Hold-suite evidence re-run green each iter; final run iter 5: **86/86**.
- ⚠ **Run the Hold filter via `php -d memory_limit=2G vendor/bin/pest --filter='HoldEnums|HoldRegistry|HoldLifecycle|ComplianceReadApi|CustomerAnonymisationHoldPrecedence|CustomerHoldsConsole|HoldStatusCoupling|CustomerHoldsChain'`.** `php artisan test --filter` on a multi-class selection = 128M bootstrap fatal (re-spawned child ignores `-d`) — not a suite failure.
- ⚠ **PG17 recipe** (close-ritual cross-engine): `docker run -d --name pg --tmpfs /var/lib/postgresql/data --shm-size=256m -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; poll `pg_isready`; prefix the 2G pest cmd with `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`; `docker rm -f pg`. **5432 = invoicing PG16 — don't reuse.**

## Active Change & Next Task
- **`reconcile-hold-registry-eight-types` — COMPLETE (5/5 tasks `[x]`), CHANGE_COMPLETE emitted.** Spec-only reconciliation adopting canon MVP-DEC-008 (Hold enum 6→8) into the two truth-specs (party-registry + operator-console) to close F4/RM-04.
- **⭐ NEXT = HUMAN, not ralph:** (1) review/merge `ralph/reconcile-hold-registry-eight-types`; (2) semantic-verify (GUIDE §2.7); (3) `openspec archive reconcile-hold-registry-eight-types --yes` (applies the 4 MODIFIED requirements into `openspec/specs/**`). **Do NOT archive/merge in the loop — humans do that after review.**
- **⭐ Giovanni hand-edit (Protected files — full table in `progress.md` 3.1 entry):** `CLAUDE.md` l.67 ✅ already done. `CONTEXT.md` STILL stale: l.371 half-fixed/self-contradictory (says "eight" but lists six + "not separate enum values"), l.372, l.215, l.222, l.234, l.379, l.380 (+l.367 optional). Recommended replacement prose is in the 3.1 progress entry.

## Blockers & Decisions Needed
- **None blocking the change** (complete). The Protected-file edits are Giovanni's (the loop cannot touch them — GUIDE §3 / invariant).
- **Deferred seams (not regressions):** Module-E trigger consumers (`CustomerChargebackFlagged`→`chargeback_review`, `StoragePaymentFailed`→`storage_payment_failed`) + `storage_payment_failed` auto-lift on `StoragePaymentSucceeded` → Phase 6. `autoLiftable()` stays `kyc`/`payment` only.
- **⚠ Number collision:** always the full token `MVP-DEC-008` (Hold enum→8, absent from frozen `spec/`).

## Open Patterns
- **Spec-reconciliation change:** code shipped ahead of truth-spec (RM-04/F4) → MODIFIED-only delta reproducing each requirement VERBATIM with surgical eight-value token changes; faithfulness proven by word-diffing delta vs live spec (2.1) + `openspec validate --strict` (2.2/final).
- **Half-fixed Protected file:** a pre-edit can swap a count token yet leave the body contradicting it (l.371 "eight" but lists six) — word-diff `git diff HEAD` + re-grep the working tree; report *current* per-line status, never the authored flag list.
- **Verify-only loop:** no production code in any task — if one seems to need it, STOP (design L1).
