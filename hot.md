---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-10
---

# Hot Cache

## Last Updated
**2026-07-10 — `parties-hero-package-residuals` CLOSED.** GUIDE §2.7 end to end: PG17 pre-merge, semantic-verify, merge `--no-ff`, archive. **No change in flight.** Local `main` is **11 commits ahead of `origin/main` — not pushed** (awaiting Giovanni).

## Build & Quality Status
- **SQLite 2389/2389** (12 404 assn) · **PG17 2389/2389** (12 411 assn) — re-run after the prose fix; identical to the 4.1 baseline, which is what proves the fix touched no behaviour.
- PHPStan **0** · Pint clean · `openspec validate --all --strict` **10/10** (one item fewer: the change left `changes/`).
- **13 files merged, ZERO under `app/`** — design R1 held. The code was always right; the spec and the suite were wrong.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 lane: prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (container `pg`, left running).

## Active Change & Next Task
- **None.** `openspec/changes/` is empty; history at `archive/2026-07-10-parties-hero-package-residuals/`.
- The archive did its one job: `party-registry:670` now reads **guard → lock → count → read → gate**, and `:717` carries the new scenario *"An out-of-state approve is rejected before any Club row is locked"*. Invariant 11 held throughout.
- **Human next:** `git push` (11 commits), then `/spec-to-change` for the next slice.

## Blockers & Decisions Needed
- **None blocking.** Two canon escalations stay OPEN, due before Module A's capacity-adjust: the capacity-decrease seat floor (`AC-K-J-15` floors on **seats**, `BR-A-Mutability-1` on **vouchers issued**); K PRD §1:77 (*S enforces*) vs §13 (*K*).
- **RM-05 closed against a documented SUBSET.** `AC-K-J-14` · `J-15` · `J-15a` · `XM-19` untouched. *"Residuals closed" ≠ "capacity is compliant"* — tracker §4.
- **Tracker §7:** **F12** `Profile↔Customer` lock-order inversion can deadlock — pre-existing, needs a *decision* before the producer HTTP surface. Also F11 · F2 · F5–F7 · F8 (→ RM-26/27) · F9 (**OTP**) · F10 (ADR).
- **Two §2.7 follow-ups, deliberately unfixed:** `party-registry:889` should state `RenewProfile`'s guard-before-lock order normatively; `knowledge/testing/hypotheses.md` has ~6 confirmations still reading *"date = archive-dir date once archived"* for changes that **are** archived — under-dating, not pre-dating.

## Open Patterns
- **An enumeration goes stale the moment the thing it enumerates grows, and nothing mechanical sees it.** Both §2.7 WARNINGs were this: the `action_failed` scenario list (2→3; task 3.2 updated `lang/`, left the docblock calling `renew` the page's *sole* rejecting verb — `approve` shares that `ViewProfile`) and the file count (11→12; a close gate reads `git diff --stat` before editing the tracker). A comment has no assertions; a count has no test. Only a reader asked to **verify the citation, not read it** catches them.
- **A close-gate sweep is a CLASSIFICATION, not a count** — three of four hit-classes are *supposed* to fire. Only a **live** doc/docblock is a defect.
- **A doc-only task must move the assertion count by ZERO.** "Green" without a baseline verifies nothing.
- **A prescribed mutant is usually the loud one** — it proves the pin fires; necessity needs the quiet drift the suite passes.
- **A sentence that orders operations is a claim, and it can ship false** — in a spec, a test's name, a tracker's status line, or the docblock of the very test that pins the corrected sentence.
