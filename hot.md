---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — `reconcile-hold-registry-eight-types` ralph iter 1: task 1.1 DONE (verify-only). The shipped eight-value Hold suite re-ran green (71/71, 399 assertions, SQLite) and every assertion the four deltas assert is present at its named point — nothing added (design D4). F4's regression-evidence leg is closed; the spec-of-record sync itself lands at archive.**

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Hold suite 71/71 green** (SQLite) this iter — 61 party-registry + 10 operator-console. Full suite 1951/1951 UNCHANGED (no code touched by this spec-only change).
- ⚠ **Run the Hold filter via `php -d memory_limit=2G vendor/bin/pest --filter='HoldEnums|HoldRegistry|HoldLifecycle|ComplianceReadApi|CustomerAnonymisationHoldPrecedence|CustomerHoldsConsole'`.** `php artisan test --filter` on this 6-class selection is a **128M bootstrap fatal** (the re-spawned child ignores `-d`) — not a suite failure. Refines the old "filtered runs fit 128M": a *multi-class* filter does not.
- ⚠ **PG17 recipe** (close-ritual cross-engine): `docker run -d --name pg --tmpfs /var/lib/postgresql/data --shm-size=256m -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; poll `pg_isready`; prefix the 2G pest cmd with `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`; `docker rm -f pg` after. **5432 = invoicing PG16 — don't reuse.**

## Active Change & Next Task
- **`reconcile-hold-registry-eight-types` — APPROVED, in-flight. 1/5 tasks done.** Spec-only reconciliation (zero code, zero test, zero migration); adopts canon MVP-DEC-008 (Hold enum 6→8) into the two truth-specs to close F4.
- **⭐ NEXT: task 1.2** — build the scenario→test traceability table in `progress.md`: every `#### Scenario:` in `specs/party-registry/spec.md` + `specs/operator-console/spec.md` maps to ≥1 assertion. Source it from the assertion inventory now in `progress.md` (Codebase Patterns + the 1.1 entry). Doc-only, no test run.
- Then **2.1** fidelity diff (each MODIFIED block vs live `openspec/specs/**` — only the 8-value tokens may move), **2.2** `openspec validate reconcile-hold-registry-eight-types --strict`, **3.1** record the Protected-file flag list in progress.md. All verify/doc-only.

## Blockers & Decisions Needed
- **🔴 PROTECTED-FILE HAND-OFF (Giovanni — the change does NOT edit these; task 3.1 only flags them):** `CLAUDE.md` l.67 `(6 types)`→`(8 types)`; `CONTEXT.md` **Hold type** glossary l.370–372 = REWRITE (still says "exactly six values … not separate enum values … inventing a seventh type" — contradicts DEC-008 + shipped code) + l.215/222/234/367/379–380. Both files already show `M` in git (pre-iteration — Giovanni's edits); ralph leaves them un-staged.
- **Deferred seams (not regressions):** Module-E trigger consumers (`CustomerChargebackFlagged`/`StoragePaymentFailed`) + `storage_payment_failed` auto-lift on `StoragePaymentSucceeded` → Phase 6. `autoLiftable()` stays `kyc`/`payment` only.
- **⚠ Number collision:** always the full token `MVP-DEC-008` (Hold enum→8, absent from frozen `spec/`).

## Open Patterns
- **Spec-reconciliation change:** code shipped ahead of the truth-spec (RM-04/F4) → the fix is a MODIFIED-only delta reproducing each requirement VERBATIM with surgical token changes, archived to sync; prove faithfulness by diffing delta vs live spec — only the intended tokens move (task 2.1).
- **Verify-only loop:** only 1.1 runs tests; 1.2/2.1/3.1 write docs, 2.2 validates. No production code in any task — if one seems to need it, STOP (design L1).
