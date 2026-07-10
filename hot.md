---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-10
---

# Hot Cache

## Last Updated
**2026-07-10 — nothing in flight; `RM-26` + `RM-27` opened; `F10` on Giovanni's desk.** `parties-hero-package-residuals` is **closed and pushed** (merge `0f75d41` / archive `1d3d941` / memory `5f25467`). Tree clean, `openspec/changes/` holds only `archive/`, `main`↔`origin` **in sync at `5f25467`** (verified via `git ls-remote`).

## Build & Quality Status
- **SQLite 2389/2389** (12 404 assn) · **PG17 2389/2389** (12 411 assn) — the PG surplus is the PG-only concurrency + CHECK lanes.
- PHPStan max **0** · Pint clean · `openspec validate --all --strict` **10/10**.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 lane: prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (container `pg`).

## Active Change & Next Task
- **None in flight.** Every *scheduled* RM item is shipped. Read `docs/validation/Remediation_Tracker.md` **§1 ▶️ NEXT** first — it is the source of truth for what remains.
- **Next, in order:** (1) **F10** — the `spec/` re-vendoring decision, **Giovanni's, not the loop's**; (2) **RM-26** (+ RM-27) via `/spec-to-change`; (3) the gated decisions (F12, the two capacity escalations); (4) pre-go-live **F2**.
- **F10 is sequenced first on purpose.** RM-26 exists *because* `spec/` is pinned at `4f48277`, **29 commits / 23 MVP-DECs behind** canon `360df0b`. Authoring RM-26 first just re-runs the discovery one `MVP-DEC` later. Needs an ADR (supersedes `2026-06-17-spec-synced-from-documentation-repo`); only `scripts/sync-spec.sh` may write `spec/` (invariant 11).

## Blockers & Decisions Needed
- **None blocking a build.** Four decisions are queued, each with a *gate*, not a date:
  - **F10** — re-vendor vs keep-frozen-plus-mandatory-live-fetch. Gate: before authoring RM-26.
  - **F12** — `Profile ↔ Customer` lock-order inversion can deadlock (`40P01`). Pre-existing, no test can see it. Gate: before the producer-facing HTTP surface.
  - **Two canon escalations on capacity** — who evaluates the seat floor (`BR-A-Mutability-1` floors on *vouchers issued*, `AC-K-J-15` on *seats*); K PRD §1:77 (*S enforces*) vs §13 (*K*). Gate: before Module A's capacity-adjust.
- **RM-05 closed against a documented SUBSET.** `AC-K-J-14` · `J-15` · `J-15a` · `XM-19` untouched. *"Residuals closed" ≠ "capacity is compliant"* — tracker §4.
- **Also open, unscheduled:** F2 (🟥 blocks go-live, not the demo) · F5 · F6 · F7 · F9 (**OTP** — watch, Paolo has not ruled) · F11 (`DemoSeeder` not re-runnable on SQLite, docblock says otherwise) · two §2.7 follow-ups (`party-registry:889` guard-before-lock prose; ~6 under-dated confirmations in `knowledge/testing/hypotheses.md`).

## Open Patterns
- **A memory file that asserts the remote's state expires by design, not by error.** Yesterday's `hot.md` said *"11 commits ahead, awaiting Giovanni"* — **true when written (13:12), false at 13:15**, falsified by the very push it recorded as pending. State what the repo *is*; never what the remote *has*. Only `git ls-remote` knows that.
- **A close ritual that stops at the merge leaves the memory stale** — the 2026-07-08 lesson ("it leaves the *backlog* stale"), one layer up. The hot cache is a §2.7 close artifact, and it is the file the `SessionStart` hook *serves*.
- **An enumeration goes stale the moment the thing it enumerates grows, and nothing mechanical sees it.** A comment has no assertions; a count has no test.
- **A verifier's finding is a candidate, not a fact** — a code-reading claim about a second method is a hypothesis, not a verification (F1, twice).
