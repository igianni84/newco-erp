---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — `catalog-review-freshness-resubmit` (RM-06) MERGED + ARCHIVED on local `main`** (merge `348dade`, archive `ad69ce2`). Close ritual (GUIDE §2.7) run interactively: PG17 **full suite 1807/1807** (9851 assertions — upgraded from the loop's 391-subset gate to the WHOLE suite on the production engine) + semantic verification (2 subagents: domain/tests + console/ADR/i18n) **both CLEAN, zero CRITICAL**. `product-catalog` truth spec absorbed the MODIFIED Approval Governance requirement (now 50 scenarios, strict-valid). RM-06 was the last Round-1 compliance-remediation item (Paolo Alfieri 2026-07-01). **Pushed to `origin/main` (`37d2cc0..2d6492d`); `ralph/` branch deleted — RM-06 FULLY CLOSED, Round 1 complete.** CI `tests-pgsql` lane re-runs the full PG17 suite at push = merge acceptance.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN on both engines, FULL suite:** SQLite **1807/1807** (9851 assertions); **PG17** (Docker `postgres:17`, GUIDE §2.7) **1807/1807** (9851 assertions, 288s) — whole suite on production engine, not just the Catalog subset. PHPStan max 0; Pint clean; `openspec validate --strict` valid.
- Full suite: `php -d memory_limit=-1 vendor/bin/pest` — bare `php artisan test` OOMs at 128M (lessons.md 2026-06-20).

## Active Change & Next Task
- **No active openspec change** — RM-06 archived (`openspec/changes/archive/2026-07-02-catalog-review-freshness-resubmit/`). Shipped: block-gate on `reviewed→active` (blocked while latest governance action ends `.rejected`) + explicit `resubmit()` (`reviewed→reviewed`, audit-only twin of `reject`), derive-from-audit (no schema). edit-re-arms leg deferred to **RM-14**; canon MVP-DEC-019.
- **Pushed + `ralph/` branch deleted** (2026-07-02) — RM-06 fully closed on code/spec/origin. CI `tests-pgsql` lane re-runs full suite on PG17 at push (development.md:86) = merge acceptance.
- **NEXT (loop, new change):** Round-1 remediation DRAINED with RM-06 — pick next backlog item from `docs/validation/Remediation_Tracker.md` / prep via `spec-to-change`.

## Blockers & Decisions Needed
- **None for RM-06** — merged, archived, pushed, branch deleted. ⚠️ Tracker RM-06 *status* lines (Remediation_Tracker.md:24/59 + change-log) still show 🟡 "awaiting commit" — a PM-voice refresh Giovanni will take (mirror the RM-24 ✅ entry pattern).
- Canon drift DEC-007→DEC-023 still open on Module K (RM-03, RM-05) — waits on Modules S/E/A.
- **Incidental findings open (Tracker §7):** F1 DemoSeeder SQLite-only; F2 prod operator-mgmt missing → SoD unsatisfiable in prod.
- ⚠️ **DEC-019 collision:** canon MVP-DEC-019 = review-freshness (RM-06); frozen spec's own DEC-019 = unrelated Module-S club composites — never conflate.

## Open Patterns
- **RM-14 latent coupling (RM-06 semantic-verify S1):** `ApprovalGovernance::assertNotRejectionPending` reads the *raw* latest catalog audit action with NO governance-verb filter — correct only because `LifecycleTransition` is the sole catalog `audit_records` writer today. When RM-14 adds the edit path (edit-audit rows), a post-rejection edit row could become "latest" and clear the block without a re-submit → **filter to governance verbs (or assert the invariant) when RM-14 lands.**
- **Review-freshness block-gate is engine-neutral** — one string column (`audit_records.action`) via `orderByDesc('id')` + PHP `str_ends_with`; full PG17 suite green with zero migration branch confirms.
- **Local PG17 gate recipe** (GUIDE §2.7): boot `postgres:17` on **55432**, `DB_CONNECTION=pgsql … php -d memory_limit=1024M vendor/bin/pest`, `docker rm -f pg`.
