---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` task 5.3 DONE — docs refresh; ralph loop, 14/15).** Docs-only task (zero executable lines — every edit inside a `/** … */` docblock or `.md`). Three parts: (1) promoted the **CONTEXT.md** Club Credit glossary stub to a full Module-K entry (entity + the 4 within-module writers Issue/Apply[K.17]/Forfeit/Restore + the audit-only §11.4 boundary); (2) marked the **freeze seam "now landed"** in CONTEXT.md (enforced at the redemption site — `ApplyClubCredit` reads the owning Profile's live `state`, rejects while `Suspended`) and refreshed the `MembershipFeePaid`-listener + cancellation-signal seam notes, **plus de-staled 6 source docblocks** (SuspendProfile/SuspendCustomer/Cancel/Deactivate/Lapse/Activate — separated the now-built Module-K credit from the genuinely-unbuilt Module-S/B/E vouchers/orders/reservations); (3) created **`knowledge/module-k/`** (audit-only-writer-for-Module-E-owned-events pattern + one-active-partial-index↔restore interaction) + INDEX row.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: full suite 1560/1560 (8500 assn) — UNCHANGED (comment/markdown-only change broke nothing); PHPStan max 0; `pint --test` clean; `openspec validate club-credit --strict` valid.**
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (laravel/pao OOMs at 128 MB; lessons.md 2026-06-20).
- **PG17:** 5.3 has NO DB touch (docs/comments only) — engine-irrelevant; nothing for the `tests-pgsql` lane to differ on.

## Active Change & Next Task
- **`club-credit` — 14/15 done. Next: 5.4 full-suite gate (the FINAL task).** Verification-only, no new code: `php -d memory_limit=-1 vendor/bin/pest` green on SQLite; the DB-touching tests also green on the **PostgreSQL 17** lane (CI `tests-pgsql` on the human push — the loop never pushes; the migration/Actions are Postgres-truthful by construction); `vendor/bin/phpstan analyse` (max) 0; `vendor/bin/pint --test` clean; `openspec validate club-credit --strict` passes. Re-verify every task's acceptance bullets at a glance, confirm all 1.1–5.3 are `[x]`, then reply **`<promise>CHANGE_COMPLETE</promise>`** (do NOT archive or merge — humans do that after review).

## Blockers & Decisions Needed
- **No active blocker.** No open ADR gate stepped through. `main` in sync with `origin/main`.
- Deferred SEAMS (not blockers — now recorded in CONTEXT.md + the glossary entry): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-110/111 + DEC-043 conversion + order-cancellation restore; year-end scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **The 4 writers (Issue/Apply/Forfeit/Restore) are COMPLETE + i18n-wired + docs-landed**, all in `SupplyLifecycleChainTest`'s `$clubCreditWriters` allow-list. 5.4 adds NO new Action/key/doc.
- **Closing a seam = grep + de-stale every breadcrumb (NEW, 5.3):** a deferred-seam change's forward-pointing "X is unbuilt" claims get scattered across many docblocks (here 5 Profile/Customer status Actions lumped Club Credit with Module-S/B/E). `grep -rniE -A1 "<entity>" app/… | grep unbuilt` finds them all; de-stale when the seam lands. Module S/E will inherit many such breadcrumbs.
- **The freeze is enforced at the REDEMPTION site, not the suspension site:** `Suspend*` stays state-preserving; `ApplyClubCredit` reads the owning Profile's live `state` to freeze. Don't reintroduce "suspension freezes the credit" phrasing.
