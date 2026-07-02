---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — Compliance-remediation Round 1 (pre-Paolo). RM-06 change authored + APPROVED (last Round-1 item); RM-24 & prior all reviewed + pushed.** Verdict reports in **`docs/validation/`**; live backlog **`Remediation_Tracker.md`**. Driven by Paolo Alfieri's 2026-07-01 mail. **On origin/main:** RM-07 `5b64cc8`, RM-04 `d8ec261`, RM-09+F3 `5eb415d`, RM-10 `04406b8`, RM-24 `4c373af`.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1769/1769**, PHPStan/Pint clean.
- Run the full suite via `php -d memory_limit=-1 vendor/bin/pest` — bare `php artisan test` OOMs at 128M (lessons.md 2026-06-20).

## Active Change & Next Task
- **Open change: `catalog-review-freshness-resubmit` (RM-06) — authored + APPROVED, 0/10 tasks, ready to build.** First Round-1 item to go through `/spec-to-change` (M — touches the shared lifecycle FSM) vs direct-TDD. `docs/validation/` answers Paolo's 3 asks: verdict (#1), env-readiness (#2), canon delta (#3).
- **Scoreboard vs local baseline:** Module 0 = 67 P / 10 Part / 7 F / 16 Def (100). Module K = 77 / 26 / 19 / 8 (130). ~70% Pass in-scope.
- **Round 1 backlog [`Remediation_Tracker.md`](docs/validation/Remediation_Tracker.md)** (25 items RM-01..25; **read it before touching any Module 0/K fix**; update it + this line as items land).
- **Done & pushed:** RM-07 `5b64cc8` · RM-04 `d8ec261` · RM-09+F3 `5eb415d` · RM-10 `04406b8` · RM-24 `4c373af`.
- **Round-1 quick wins closed** (detail in tracker §3/§6): RM-07 (operators+SoD fixture) · RM-04 (Hold 6→8) · RM-09+F3 (erasure-ADR honesty) · RM-10 (ClubCredit `Accrued` rename) · RM-24 (Product-Type immutable guard, DEC-023). Each with mini-ADR where a canon-DEC was adopted. Suite 1769/1769.
- **Active next:** implement **`catalog-review-freshness-resubmit`** (RM-06) — block-gate on `reviewed→active` + explicit `re-submit` (`reviewed→reviewed`, twin of `reject`) across all 7 spine entities, **derive-from-audit** (no schema); completes the prior change's design-D5 intent. edit-re-arms→RM-14; mini-ADR MVP-DEC-019. APPROVED, awaiting commit + build (ralph or interactive TDD). Closes Round 1. Then Round 2 = floor builds (RM-01 erasure / RM-02 enhanced-KYC).

## Blockers & Decisions Needed
- **Canon drift DEC-007 → DEC-023** (heaviest on Module K). Headline divergences vs code: membership "approved-but-unpaid" flow **DEC-016 declared WRONG** (RM-03); capacity ships **UNCAPPED** vs DEC-017 seat-set (RM-05) — both wait on Modules S/E/A.
- **Floor gaps (do NOT defer, Round 2):** GDPR erasure/anonymisation absent (K J-9/9a/FSM-16; no Address entity — RM-01); enhanced-KYC €10k/€50k threshold absent (seam columns only — RM-02).
- **Rule now firm (lessons.md 2026-07-02):** adopting a canon-DEC absent from frozen `spec/` → ALWAYS a mini-ADR (confirmed 3×: RM-04/10/24); the tracker "ADR?" column is advisory. Applies to RM-03/05/23.

## Open Patterns
- **Escalation-asymmetry (memory `spec-divergence-from-cmless-documentation`) confirmed live:** our frozen handoff carried the wrong membership flow; canon DEC-008..023 never crossed. This validation IS the corrections-inbox — deltas → local ADRs, genuine gaps → c-mless issues.
- **Divergent module maturity:** Catalog enforces SoD + now Product-Type immutability; Parties does NOT enforce SoD (RM-08).
- **Incidental findings open (Tracker §7):** F1 DemoSeeder SQLite-only (PG-truncate); F2 prod operator-mgmt missing → SoD unsatisfiable in prod. Convention: log any incidental discovery in §7 — never drop it.
