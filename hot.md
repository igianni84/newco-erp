---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — RM-03 `parties-membership-charge-on-approval` COMPLETE (5/5 tasks green). Atomic approve = charge = activation shipped: `ApproveProfile` drives `Applied → Approved(transient) → Active` in one tx; `activate` console verb retired; `MembershipFeePaid` seam re-homed E→S (docblock, INV1/no-INV0). Full suite 1951/1951 on SQLite AND PG17, PHPStan max 0, Pint clean, `openspec validate --strict` valid. → CHANGE_COMPLETE signalled.**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Latest green (BOTH engines): full suite 1951/1951**, 10419 assertions. RM-03 added/renamed no Action/Event class, no migration, no dependency.
- ⚠ **Full suite = `php -d memory_limit=2G vendor/bin/pest`** (`php artisan test` re-spawns a child ignoring `-d` → 128M fatal; on PG the pao teardown also swallows the JSON summary). Filtered runs fit 128M.
- ⚠ **PG17 recipe:** `docker run -d --name pg --tmpfs /var/lib/postgresql/data --shm-size=256m -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; poll `docker exec pg pg_isready`; prefix the 2G pest cmd with `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`; `docker rm -f pg` after. **5432 = invoicing PG16 — don't reuse.** phpunit.xml's `DB_CONNECTION=sqlite` is un-`force`d → shell env overrides it.
- ⚠ **Bare path/dir on `OperatorPanel/**` reds the `*ConsoleI18nTest`s** (scanner declared in `ProductMasterConsoleI18nTest`, full-suite-only) — append that file to run one alone. Not a regression.

## Active Change & Next Task
- **RM-03 DONE — `<promise>CHANGE_COMPLETE</promise>` signalled.** Branch `ralph/parties-membership-charge-on-approval`, commits `2fb7539`→(4.1). Humans review/merge/archive. Authority ADR: `decisions/2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval.md`. The archive step assigns the new `knowledge/testing` FSM-shape-flip hypothesis its canonical confirmation date — **do NOT back-date to today**.
- **⭐ NEXT (after human merge/archive): author the next Remediation item** via `/spec-to-change`, grounded on LIVE canon (`git -C ../documentation fetch cmless main` — read the real register + acceptance, never the overlay summary). RM candidates below.

## Blockers & Decisions Needed
- **⚠ FLAG for Giovanni (RM-03 IT copy):** approve notification rendered **"Adesione approvata e attivata."** (the `lang/it` block's «membership»→«adesione» convention, ~L630) over tasks.md's literal "Iscrizione…". Design marked it "subject to Giovanni's review" — one-word revert if he prefers "Iscrizione".
- **Deferred by RM-03 (forward seams, not regressions):** real charge (mandate/instrument/`fee_paid_at`/invoice + `MembershipFeePaid` emitter) → Module S/E (F4–F6); Hero-Package **seat gate** (MVP-DEC-017) → **RM-05** (⏸️ Module A `qty`) — membership UNCAPPED at the atomic instant until then; SoD/four-eyes → **RM-08**.
- **F4/RM-04 candidate:** truth-spec *Hold Registry* still "six-value" vs code's 8 (DEC-008 already ADR'd — adoption debt).
- **⚠ Number collision:** `MVP-DEC-016` (membership) ≠ greenfield `DEC-016` (AI-copilot) — always the full token.

## Open Patterns
- **FSM state-shape flip = invert every observer in ONE commit** (now a `knowledge/testing` 1/3 hypothesis): grep the state token + Action name to enumerate; sort into outcome-observers (flip) / precondition-helpers (delete the illegal 2nd call) / factory-forced datasets (leave) / source-scan guards (diff-free iff no class added & side-write count unchanged). Isolated writer's contract stands unedited.
- **Relocate-before-delete (lesson 2026-07-03):** a task's "delete file X" can under-describe X's coverage — grep first, rehome orthogonal coverage, then delete.
- **Canon-adoption ADRs source from LIVE canon (lesson 2026-07-03),** not frozen `spec/` or the validation overlay's summary — every `MVP-DEC-NNN` absent from our snapshot earns a mini-ADR.
