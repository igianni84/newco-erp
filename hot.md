---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — `reconcile-hold-registry-eight-types` ralph iter 2: task 1.2 DONE (doc-only). Built the scenario→test traceability table in `progress.md`: all 19 `#### Scenario:` across the two deltas map to ≥1 green assertion. 0 unmapped → 0 new tests (design D4 confirmed). Verified the console Lift-visibility predicate is `active && !autoLiftable()` (`CustomerHoldsTable.php:311`) — the delta's six-liftable-rows claim (design L3) is faithful to shipped code. 2/5 done.**

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **86/86 green** re-run this iter across the 9 cited test files (571 assertions, ~2.9s, SQLite) = 1.1's six-class 71 + `HoldStatusCouplingPlaceTest` 6 + `HoldStatusCouplingLiftTest` 8 + `CustomerHoldsChainTest` 1. Full suite 1951/1951 UNCHANGED (spec-only change, zero code touched).
- ⚠ **Run the Hold filter via `php -d memory_limit=2G vendor/bin/pest --filter='HoldEnums|HoldRegistry|HoldLifecycle|ComplianceReadApi|CustomerAnonymisationHoldPrecedence|CustomerHoldsConsole|HoldStatusCoupling|CustomerHoldsChain'`.** `php artisan test --filter` on a multi-class selection = 128M bootstrap fatal (the re-spawned child ignores `-d`) — not a suite failure.
- ⚠ **PG17 recipe** (close-ritual cross-engine): `docker run -d --name pg --tmpfs /var/lib/postgresql/data --shm-size=256m -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; poll `pg_isready`; prefix the 2G pest cmd with `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`; `docker rm -f pg` after. **5432 = invoicing PG16 — don't reuse.**

## Active Change & Next Task
- **`reconcile-hold-registry-eight-types` — APPROVED, in-flight. 2/5 tasks done.** Spec-only reconciliation (zero code/test/migration); adopts canon MVP-DEC-008 (Hold enum 6→8) into the two truth-specs to close F4.
- **⭐ NEXT: task 2.1** — fidelity diff: diff each MODIFIED block in the two delta files against live `openspec/specs/party-registry/spec.md` / `operator-console/spec.md`; confirm the ONLY semantic change is the eight-value reconciliation (six→eight, the two type names, operator-liftable list `admin/fraud/compliance/credit`→+`chargeback_review`/`storage_payment_failed`, the DEC-008/`AC-K-FSM-10/11`/`AC-K-EVT-18/19` citations + "eight types" source notes). Any other divergence = accidental edit → revert (design L2). Doc/verify-only.
- Then **2.2** `openspec validate reconcile-hold-registry-eight-types --strict`; **3.1** record the Protected-file flag list in progress.md. All verify/doc-only.

## Blockers & Decisions Needed
- **🔴 PROTECTED-FILE HAND-OFF (Giovanni — the change does NOT edit these; task 3.1 only flags them):** `CLAUDE.md` l.67 `(6 types)`→`(8 types)`; `CONTEXT.md` **Hold type** glossary l.370–372 = REWRITE (still says "exactly six values … inventing a seventh type" — contradicts DEC-008 + shipped code) + l.215/222/234/367/379–380. Both files already show `M` in git (Giovanni's pre-iteration edits); ralph leaves them un-staged.
- **Deferred seams (not regressions):** Module-E trigger consumers (`CustomerChargebackFlagged`/`StoragePaymentFailed`) + `storage_payment_failed` auto-lift on `StoragePaymentSucceeded` → Phase 6. `autoLiftable()` stays `kyc`/`payment` only.
- **⚠ Number collision:** always the full token `MVP-DEC-008` (Hold enum→8, absent from frozen `spec/`).

## Open Patterns
- **Spec-reconciliation change:** code shipped ahead of the truth-spec (RM-04/F4) → the fix is a MODIFIED-only delta reproducing each requirement VERBATIM with surgical eight-value token changes, archived to sync; prove faithfulness by diffing delta vs live spec (task 2.1).
- **Traceability method (1.2):** MODIFIED-delta scenarios split into changed (eight-value → verification classes) vs carried-verbatim/count-independent (→ pre-existing green tests, maybe outside the filter). Verify the PREDICATE, not just the test's dataset (`!autoLiftable()` entails the two new types' console visibility).
- **Verify-only loop:** no production code in any task — if one seems to need it, STOP (design L1).
