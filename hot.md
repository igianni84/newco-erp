---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-03
---

# Hot Cache

## Last Updated
**2026-07-03 — RM-03 ADR authored (MVP-DEC-016 membership charge-on-approval), canon-grounded.** Round-2 P0 floor (RM-01 + RM-02) shipped + merged + archived + **pushed** (`main`↔`origin` in sync). RM-03 ADR done via grill-with-docs, grounded on **live canon** (Giovanni's redirect). **No active OpenSpec change.** Next = `/spec-to-change` for RM-03.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Latest green: full suite 1947/1947 on SQLite AND PG17** (10459 assertions) at RM-02 task 7.1; PHPStan max 0, Pint clean. No code touched since (RM-03 = ADR/docs only so far).
- ⚠ **Full suite = `php -d memory_limit=2G vendor/bin/pest`** (`php artisan test` re-spawns a child ignoring `-d` → 128M fatal in the Filament panel tests, NOT a regression). Filtered runs fit 128M.
- ⚠ **Local PG cross-engine:** `postgres:17` docker with `--tmpfs …/data` + `--shm-size=256m` on a free port (5432 held by invoicing PG16 → 55432); full suite via the 2G pest cmd; `docker rm -f pg` after.

## Active Change & Next Task
- **No active OpenSpec change** (`openspec list` empty).
- **RM-03 ADR authored:** `decisions/2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval.md` (+ INDEX). **Decision = Option B (canon):** keep `Approved` **TRANSIENT** (`Applied → Approved → Active` atomic, never durable) — NOT remove it (AC-K-FSM-2 enumerates `Approved → Active` → removing fails it). Charge-fail → stays `Applied`, no seat, no OC lock. `MembershipFeePaid` re-home **E→S** (docblock-only, no event class). **INV1, no INV0.** Real charge (mandate-at-application, card/SEPA, `fee_paid_at`, invoice) deferred **Module S/E** (stubs); seat gate (`Active`+`Suspended`, MVP-DEC-017) deferred **RM-05** (Module A `qty`).
- **⭐ NEXT: `/spec-to-change` for RM-03** → human `APPROVED` → `./ralph.sh`. Scope today = K-side shape-collapse + seam re-home + INV1 target; `ApproveProfile` drives through to `Active` synchronously (K-internal activate-on-approval that later delegates to Module-S `MembershipFeePaid`). Tests to invert: `MembershipActivationChainTest`, `ProfileMembershipChainTest`, the two console verbs.
- Knowledge-promotion confirmation date for RM-03 work = its future archive-dir date.

## Blockers & Decisions Needed
- **None blocking.** RM-05 (capacity) stays ⏸️ pending Module A.
- **⚠ Number collision:** `MVP-DEC-016` (membership) ≠ greenfield `DEC-016` (AI-copilot, superseded by DEC-021) — always the full token.
- **Canon grounding:** our `spec/` is frozen @ `4f48277` (MVP-DEC-007); canon `main` @ `6f3c2f8` (+23). For any canon-adoption, read-only `git -C ../documentation fetch cmless main` + read the real `MVP_Decisions_Register` + changed ACs (`lessons.md` 2026-07-03).

## Open Patterns
- **F4 candidate (untriaged):** truth-spec *Hold Registry* still "six-value" vs code's 8 (RM-04 debt) — §7 row in the tracker.
- **Durable landmines RM-02 (do NOT "fix"):** console AML `under_review` re-tags `trigger_source=compliance_ad_hoc`; sanctions-clear leaves `enhanced_kyc_flag=true` + review `resolved_at=NULL`.
- **Closing integration test = drive the chain through the REAL Actions, assert the emergent event-SET** (`DomainEvent::query()->distinct()->pluck('name')->toEqualCanonicalizing([...])`) — a `knowledge/testing` rule.
