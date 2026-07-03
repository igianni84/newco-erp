---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — `reconcile-hold-registry-eight-types` AUTHORED + APPROVED (`/spec-to-change`), ready for ralph. Closes F4 (tracker §7 — the "RM-04 delta debt"): RM-04's Hold enum 6→8 shipped in `d8ec261` (2026-07-01, approved + pushed) but as a direct commit OUTSIDE the OpenSpec flow, so the two truth-specs never synced. This is a SPEC-ONLY reconciliation adopting canon MVP-DEC-008 into the spec-of-record — ZERO production code, zero new test, zero migration. `openspec validate --strict` green.**

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Full suite 1951/1951** (last green at RM-03 merge, both engines) — UNCHANGED this session (no code touched). The 71 Hold tests the change cites re-run green (61 party-registry + 10 operator-console `CustomerHoldsConsole`).
- ⚠ **Full suite = `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child ignoring `-d` → 128M fatal; filtered runs fit 128M.
- ⚠ **PG17 recipe:** `docker run -d --name pg --tmpfs /var/lib/postgresql/data --shm-size=256m -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; poll `pg_isready`; prefix the 2G pest cmd with `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`; `docker rm -f pg` after. **5432 = invoicing PG16 — don't reuse.**
- ⚠ **Bare path/dir on `OperatorPanel/**` reds the `*ConsoleI18nTest`s** (full-suite-only scanner) — append that file to run one alone. Not a regression.

## Active Change & Next Task
- **`reconcile-hold-registry-eight-types` — APPROVED, in-flight.** 4 requirement MODIFY (party-registry: *Hold Registry*, *Hold Lifecycle and Lift Discipline*, *Hold-Driven Status Coupling*; operator-console: *Operator places and lifts Customer Holds*) — six-value → eight-value; the 2 finance-driven types `chargeback_review`/`storage_payment_failed` operator-lift-only at launch. 5 tasks, all verify-only. `autoLiftable()` stays `kyc`/`payment`.
- **⭐ NEXT:** `./ralph.sh --change reconcile-hold-registry-eight-types 1` (verify-only: suite green + validate + scenario→test traceability in progress.md) → review → archive (syncs the truth-specs → F4 CLOSED). Verify-only, so Giovanni MAY instead verify + `openspec archive` directly, no ralph.

## Blockers & Decisions Needed
- **🔴 PROTECTED-FILE HAND-OFF (Giovanni, GUIDE §3 — the change does NOT touch these):** still say "six/6 types". `CLAUDE.md` l.67 `(6 types)`→`(8 types)`. `CONTEXT.md` **Hold type** glossary l.370–372 = REWRITE (states "exactly six values … not separate enum values … Avoid: inventing a seventh type" — actively contradicts DEC-008 + shipped code); plus l.215, l.222, l.234, l.379–380, l.367.
- **Deferred seams (not regressions):** Module-E trigger consumers (`CustomerChargebackFlagged`/`StoragePaymentFailed`) + `storage_payment_failed` auto-lift on `StoragePaymentSucceeded` → Phase 6; root `CLAUDE.md` Invariant #7 reword → standing ADR item (`2026-06-18-hold-lift-discipline-per-type`).
- **⚠ Number collision:** canon `MVP-DEC-008` (Hold enum→8, absent from frozen `spec/`) — always the full token.

## Open Patterns
- **Spec-reconciliation change (NEW this session):** when code shipped ahead of the truth-spec (a direct commit outside OpenSpec — the RM-04/F4 shape), the only fix is a MODIFIED-only delta reproducing the requirement VERBATIM with surgical token changes, archived to sync. Prove faithfulness by mechanically diffing the delta vs the live spec (only the intended tokens may move).
- **Canon-adoption ADRs source from LIVE canon** (lesson 2026-07-03), not frozen `spec/` nor the validation overlay; each absent `MVP-DEC-NNN` earns a mini-ADR — DEC-008 already ADR'd (`2026-07-01`), so this change just ADOPTS it into the spec-of-record.
