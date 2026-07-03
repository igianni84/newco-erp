---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — `reconcile-hold-registry-eight-types` ralph iter 3: task 2.1 DONE (fidelity diff, verify-only). Word-diffed all 4 MODIFIED requirements (delta vs live `openspec/specs/**`): the ONLY semantic change in every block is the eight-value reconciliation — no accidental spec edit, nothing reverted (design L2 gate passed). *Hold-Driven Status Coupling* = source-note-only (`six types;`→`**eight** types — canon MVP-DEC-008;`), body + 3 scenarios byte-identical ✓. Change surface = exactly 4 MODIFIED requirements (party-registry 3 + operator-console 1), no stray ADDED/REMOVED. 3/5 done.**

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **No code/test/spec/PHP touched this iter** (fidelity diff is doc-only) → format/test/type_check/lint N/A. Suite last green at 1.2: **86/86** across the 9 cited Hold test files (571 assertions, ~2.9s, SQLite); full suite 1951/1951 UNCHANGED (spec-only change).
- ⚠ **Run the Hold filter via `php -d memory_limit=2G vendor/bin/pest --filter='HoldEnums|HoldRegistry|HoldLifecycle|ComplianceReadApi|CustomerAnonymisationHoldPrecedence|CustomerHoldsConsole|HoldStatusCoupling|CustomerHoldsChain'`.** `php artisan test --filter` on a multi-class selection = 128M bootstrap fatal (re-spawned child ignores `-d`) — not a suite failure.
- ⚠ **PG17 recipe** (close-ritual cross-engine): `docker run -d --name pg --tmpfs /var/lib/postgresql/data --shm-size=256m -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; poll `pg_isready`; prefix the 2G pest cmd with `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`; `docker rm -f pg` after. **5432 = invoicing PG16 — don't reuse.**

## Active Change & Next Task
- **`reconcile-hold-registry-eight-types` — APPROVED, in-flight. 3/5 tasks done.** Spec-only reconciliation (zero code/test/migration); adopts canon MVP-DEC-008 (Hold enum 6→8) into the two truth-specs to close F4.
- **⭐ NEXT: task 2.2** — `openspec validate reconcile-hold-registry-eight-types --strict` must pass (green; fix structure until it does). Already passed at 1.2 — re-confirm + record. Doc/verify-only.
- Then **3.1** — transcribe the Protected-file "six/6 types" flag list into `progress.md` for Giovanni's hand-edit (see Blockers); edit NO Protected file. All verify/doc-only. Then all-tasks-done → `<promise>CHANGE_COMPLETE</promise>`.
- **Fidelity-diff method (2.1, reusable):** extract each MODIFIED block by exact name (`awk '/^### Requirement: /{p=0} /^### Requirement: <NAME>$/{p=1} p'`) from delta AND live, then `git diff --no-index --word-diff-regex='[^[:space:]]+'`. Trailing-blank −1 hunk at `_Source:` = awk artifact (live runs into next requirement's blank line), not dropped content — confirm via tail.

## Blockers & Decisions Needed
- **🔴 PROTECTED-FILE HAND-OFF (Giovanni — the change does NOT edit these; task 3.1 only flags them):** `CLAUDE.md` l.67 `(6 types)`→`(8 types)`; `CONTEXT.md` **Hold type** glossary l.370–372 = REWRITE (still says "exactly six values … inventing a seventh type" — contradicts DEC-008 + shipped code) + l.215/222/234/367/379–380. Both files already show `M` in git (Giovanni's pre-iteration edits); ralph leaves them un-staged.
- **Deferred seams (not regressions):** Module-E trigger consumers (`CustomerChargebackFlagged`/`StoragePaymentFailed`) + `storage_payment_failed` auto-lift on `StoragePaymentSucceeded` → Phase 6. `autoLiftable()` stays `kyc`/`payment` only.
- **⚠ Number collision:** always the full token `MVP-DEC-008` (Hold enum→8, absent from frozen `spec/`).

## Open Patterns
- **Spec-reconciliation change:** code shipped ahead of the truth-spec (RM-04/F4) → MODIFIED-only delta reproducing each requirement VERBATIM with surgical eight-value token changes, archived to sync; prove faithfulness by word-diffing delta vs live spec (2.1 done: passed).
- **In-scope vs accidental spec edit (L2):** an addition is in-scope iff it's a six→eight token, a type name, a DEC-008/AC-family citation, the operator-lift-only-at-launch+deferred-seam framing (D3/L4), or the `HoldType::cases()`-derivation sentence (all in proposal "What Changes"). Touching an unrelated normative clause = accidental → revert. None found.
- **Verify-only loop:** no production code in any task — if one seems to need it, STOP (design L1).
