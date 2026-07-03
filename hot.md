---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — RM-03 `parties-membership-charge-on-approval` MERGED (`892ccf8`) + ARCHIVED (`e9892b9`) via the §2.7 close ritual. Atomic approve = charge = activation is now LIVE truth: `ApproveProfile` drives `Applied → Approved(transient) → Active` in one tx; `activate` console verb retired; `MembershipFeePaid` seam re-homed E→S (docblock; INV1/no-INV0). The 4 delta requirements folded into `openspec/specs/party-registry/spec.md`; the change now lives under `openspec/changes/archive/2026-07-03-…`. Semantic verify: WARNINGS-ONLY, no CRITICAL. The RM-03 arc is pushed to `origin/main`.**

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Full suite 1951/1951** (10419 assertions) green BOTH engines: SQLite re-run at merge; PG17 from the 3.1 cross-engine gate (4.1 was memory-only, identical tree). PHPStan max 0, Pint clean.
- ⚠ **Full suite = `php -d memory_limit=2G vendor/bin/pest`** — `php artisan test` re-spawns a child ignoring `-d` → 128M fatal; filtered runs fit 128M.
- ⚠ **PG17 recipe:** `docker run -d --name pg --tmpfs /var/lib/postgresql/data --shm-size=256m -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; poll `pg_isready`; prefix the 2G pest cmd with `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`; `docker rm -f pg` after. **5432 = invoicing PG16 — don't reuse.**
- ⚠ **Bare path/dir on `OperatorPanel/**` reds the `*ConsoleI18nTest`s** (scanner in `ProductMasterConsoleI18nTest`, full-suite-only) — append that file to run one alone. Not a regression.

## Active Change & Next Task
- **RM-03 CLOSED — no in-flight change** (`openspec list` empty).
- **⭐ NEXT: author the next Remediation item** via `/spec-to-change`, grounded on LIVE canon (`git -C ../documentation fetch cmless main` — read the real register + acceptance, never the overlay). Candidates: **RM-04** (Hold Registry 6→8, DEC-008 already ADR'd — adoption debt); **RM-05** (Hero-Package seat gate MVP-DEC-017, ⏸ blocked on Module A `qty`); **RM-08** (SoD/four-eyes on approval).

## Blockers & Decisions Needed
- **⚠ FLAG (RM-03 IT copy):** approve notification shipped **"Adesione approvata e attivata."** (the `lang/it` «adesione» convention) over tasks.md's "Iscrizione…" — one-word revert if Giovanni prefers "Iscrizione".
- **Deferred by RM-03 (forward seams, not regressions):** real charge (mandate/instrument/`fee_paid_at`/invoice + `MembershipFeePaid` emitter) → Module S/E (F4–F6); Hero-Package seat gate → RM-05 (membership UNCAPPED at the atomic instant until then); SoD → RM-08.
- **SUGGESTION (semantic verify):** `ProfileActivationTest.php` docblock/test-name still say "Module E" (stale post E→S; forced by landmine #8 "test stands unchanged") — future one-line prose cleanup, assertions valid.
- **⚠ Number collision:** `MVP-DEC-016` (membership) ≠ greenfield `DEC-016` (AI-copilot) — always the full token.

## Open Patterns
- **FSM state-shape flip = invert every observer in ONE commit** (`knowledge/testing` 1/3 hypothesis, RM-03 confirmed): grep state token + Action name; sort into outcome-observers (flip) / precondition-helpers (delete illegal 2nd call) / factory-forced sets (leave) / source-scan guards (diff-free iff no class added & side-write count unchanged).
- **Relocate-before-delete (lesson 2026-07-03):** "delete file X" can under-describe X's coverage — grep first, rehome, then delete (RM-03: `ProfileActivationConsoleTest`→`ProfileStatusConsoleTest`).
- **Canon-adoption ADRs source from LIVE canon (lesson 2026-07-03),** not frozen `spec/` or the overlay — each absent `MVP-DEC-NNN` earns a mini-ADR.
