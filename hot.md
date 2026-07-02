---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-01 (`parties-anonymisation`) change AUTHORED via `/spec-to-change`, `validate --strict` GREEN, and APPROVED by Giovanni — `APPROVED` marker created. READY TO BUILD; ralph NOT yet launched (prep-only session).** GDPR right-to-erasure/anonymisation + Address entity (Module K), the Round-2 P0 compliance-floor headline. **Next step = the build: `./ralph.sh --change parties-anonymisation <n>` or interactive (one task/iter), start at task 1.1 (mini-ADR MVP-DEC-015) — awaiting Giovanni's go on when/how to launch.** Two crux decisions Giovanni-confirmed: **(Q1)** Hold-block-set = canon **MVP-DEC-015 `compliance`-only** (mini-ADR; resolves the frozen-spec DEC-027-vs-PRD contradiction; sanctioned-customer retention via a `compliance` Hold — there is no `sanctions` Hold type, sanctions is the `sanctions_status` FSM); **(Q2)** J-9b export = **minimal synchronous in-memory** (no file persistence → no object-storage ADR tripped).

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **No code touched this session — prep only.** `main` == `origin/main` @ `20d63b8`, tree clean. Last green (RM-06 close): SQLite + PG17 **1807/1807** (9851 assertions); PHPStan max 0; Pint clean.
- Full suite: `php -d memory_limit=-1 vendor/bin/pest` (bare `php artisan test` OOMs at 128M).

## Active Change & Next Task
- **Active change: `parties-anonymisation` (RM-01) — AUTHORED, validate-strict GREEN, APPROVED ✅ (2026-07-02).** 4 artifacts: proposal / design (D1–D7) / `specs/party-registry/spec.md` (**4 ADDED + 1 MODIFIED** requirement) / tasks (**12**, groups 1–7). APPROVED marker present; **not yet built.**
- Scope: `AnonymiseCustomer` (compliance-Hold gate → PII overwrite-in-place w/ deterministic id-keyed placeholders → `anonymised_at` → audit-records redaction → PII-free `CustomerAnonymised` event; keyed history preserved; orthogonal to status FSM); Address entity (`parties_addresses`, billing, DEC-068); `ExportCustomerData` (J-9b minimal). 2 additive PG-truthful migrations.
- **NEXT: build.** APPROVED ✅ → `./ralph.sh --change parties-anonymisation <n>` (or interactive, one task/iter), start at task 1.1 (mini-ADR MVP-DEC-015). NOT launched this session per the prep-only brief — awaiting go-signal. After build: close ritual §2.7 (PG17 full suite + semantic-verify) → archive.

## Blockers & Decisions Needed
- **RM-01 APPROVED ✅ (2026-07-02).** Both design Qs (Q1/Q2) resolved. Next gate = build-launch decision (ralph vs interactive) — not started this session (prep-only brief).
- **New incidental (candidate F4):** `party-registry` truth-spec *Hold Registry* still says "six-value" Hold domain while code is **8** (RM-04 shipped code + mini-ADR but authored no OpenSpec delta). RM-01's Hold-precedence is phrased `compliance`-only (count-independent) so it does NOT block; flagged in the proposal slice-boundary — decide fold-in vs file.
- Canon drift MVP-DEC-007→023 still open on Module K (RM-03/DEC-016, RM-05/DEC-011/017) — waits on Modules S/E/A. Tracker §7: F1, F2 still open.

## Open Patterns
- **Canon-ADR discipline (lessons.md 2026-07-02, now a rule — 4th application):** adopting a canon MVP-DEC absent from frozen `spec/` (stops at 007) always earns a mini-ADR — RM-01/DEC-015 is tasks.md 1.1. The tracker "ADR? —" column is advisory.
- **Erasure seam already built** (event-substrate + identity-auth ADRs): the `audit_records` before/after redaction path + the PG `redactor` role reserved "for Module K's erasure job (a later change)" — RM-01 IS that change; completing it makes RM-09's corrected claim fully truthful.
- **RM-14 latent coupling (RM-06 semantic-verify S1):** `assertNotRejectionPending` reads the raw latest catalog audit action, no verb filter — when RM-14 adds the edit path, filter to governance verbs.
