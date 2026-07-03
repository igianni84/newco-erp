---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — `reconcile-hold-registry-eight-types` ralph iter 4: task 2.2 DONE (verify-only). `openspec validate reconcile-hold-registry-eight-types --strict` → `is valid`, exit 0 (RALPH §d hard gate for a delta-spec change) — green on first run, no structure fix. Re-confirms the 1.2 pass; delta structure unmoved since (2.1 was a read-only diff). ZERO code/test/spec/PHP/Protected-file touched. 4/5 done — only 3.1 (Protected-file flag list → progress.md) remains, then CHANGE_COMPLETE.**

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **No code/test/spec/PHP touched this iter** (validate is doc-only) → format/test/type_check/lint N/A. Suite last green at 1.2: **86/86** across the 9 cited Hold test files (571 assertions, ~2.9s, SQLite); full suite 1951/1951 UNCHANGED (spec-only change).
- ⚠ **Run the Hold filter via `php -d memory_limit=2G vendor/bin/pest --filter='HoldEnums|HoldRegistry|HoldLifecycle|ComplianceReadApi|CustomerAnonymisationHoldPrecedence|CustomerHoldsConsole|HoldStatusCoupling|CustomerHoldsChain'`.** `php artisan test --filter` on a multi-class selection = 128M bootstrap fatal (re-spawned child ignores `-d`) — not a suite failure.
- ⚠ **PG17 recipe** (close-ritual cross-engine): `docker run -d --name pg --tmpfs /var/lib/postgresql/data --shm-size=256m -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; poll `pg_isready`; prefix the 2G pest cmd with `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`; `docker rm -f pg` after. **5432 = invoicing PG16 — don't reuse.**

## Active Change & Next Task
- **`reconcile-hold-registry-eight-types` — APPROVED, in-flight. 4/5 tasks done.** Spec-only reconciliation (zero code/test/migration); adopts canon MVP-DEC-008 (Hold enum 6→8) into the two truth-specs to close F4.
- **⭐ NEXT (last task): 3.1** — transcribe the still-"six/6 types" Protected-file lines into `progress.md` for Giovanni's hand-edit (list is in Blockers below + proposal Impact). **Edit NO Protected file** — the change's diff must touch none. Verify/doc-only. Then all tasks `[x]` → final re-verify (all acceptance bullets + `openspec validate --strict` green + hot.md updated) → reply `<promise>CHANGE_COMPLETE</promise>`.
- **Do NOT archive or merge** — humans do that after review.

## Blockers & Decisions Needed
- **🔴 PROTECTED-FILE HAND-OFF (Giovanni — the change does NOT edit these; task 3.1 only flags them in progress.md):** `CLAUDE.md` l.67 `(6 types)`→`(8 types)`; `CONTEXT.md` **Hold type** glossary l.370–372 = REWRITE (still says "exactly six values … inventing a seventh type" — contradicts DEC-008 + shipped code) + l.215/222/234/367/379–380. Both files already show `M` in git (Giovanni's pre-iteration edits); ralph leaves them un-staged. Confirm exact current line numbers when writing 3.1.
- **Deferred seams (not regressions):** Module-E trigger consumers (`CustomerChargebackFlagged`/`StoragePaymentFailed`) + `storage_payment_failed` auto-lift on `StoragePaymentSucceeded` → Phase 6. `autoLiftable()` stays `kyc`/`payment` only.
- **⚠ Number collision:** always the full token `MVP-DEC-008` (Hold enum→8, absent from frozen `spec/`).

## Open Patterns
- **Spec-reconciliation change:** code shipped ahead of the truth-spec (RM-04/F4) → MODIFIED-only delta reproducing each requirement VERBATIM with surgical eight-value token changes, archived to sync; faithfulness proven by word-diffing delta vs live spec (2.1 passed) and `openspec validate --strict` (2.2 passed).
- **Verify-only loop:** no production code in any task — if one seems to need it, STOP (design L1).
