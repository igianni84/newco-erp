---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — Compliance-remediation Round 1 (pre-Paolo). RM-07/04/09 reviewed & pushed; RM-10 done (awaiting review).** Verdict reports in **`docs/validation/`**; live backlog **`Remediation_Tracker.md`**. Driven by Paolo Alfieri's 2026-07-01 mail. **On origin/main:** RM-07 `5b64cc8`, RM-04 `d8ec261`, RM-09+F3 `5eb415d`, RM-10 `04406b8`.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1767/1767**, PHPStan/Pint clean.
- Run the full suite via `php -d memory_limit=-1 vendor/bin/pest` — bare `php artisan test` OOMs at 128M (lessons.md 2026-06-20).

## Active Change & Next Task
- No openspec change open (RM-07 was size-S → coded directly, TDD). `docs/validation/` answers Paolo's 3 asks: verdict (#1), env-readiness (#2), canon delta (#3).
- **Scoreboard vs local baseline:** Module 0 = 67 P / 10 Part / 7 F / 16 Def (100). Module K = 77 / 26 / 19 / 8 (130). ~70% Pass in-scope.
- **Compliance-remediation Round 1 → backlog [`Remediation_Tracker.md`](docs/validation/Remediation_Tracker.md)** (25 items RM-01..25; **read it before touching any Module 0/K fix**; update it + this line as items land).
- **Done & pushed:** RM-07 `5b64cc8` (demo operators + SoD fixture) · RM-04 `d8ec261` (`HoldType` 6→8) · RM-09+F3 `5eb415d` (identity-auth + substrate ADR erasure overclaim, in-place; both ADRs now consistent).
- **RM-10 ✅ done (awaiting review) — canon DEC-018:** ClubCredit issuance event `ClubCreditIssued`→`ClubCreditAccrued`. Mini-ADR `2026-07-02-adopt-dec-018-clubcredit-accrued` (+INDEX) — **reverses** our coherent frozen-spec DEC-166, so needs the trace despite §3 "—" (Giovanni-approved). EVENT seam-name only (no event class) → zero behaviour delta; K writer vocab unchanged; application→Module-S + `MembershipFeePaid`(RM-03) deferred seams. Suite 1767/1767, PHPStan/Pint clean. Pushed `04406b8`.
- **Active next:** **RM-24** (Product-Type immutability guard) — last Round-1 quick win before the floor builds (RM-01/RM-02).

## Blockers & Decisions Needed
- **Canon drift is DEC-007 → DEC-023** (16 new), heaviest on Module K. Three headline divergences vs code: (1) membership "approved-but-unpaid" flow **DEC-016 declared WRONG**; (2) capacity ships **UNCAPPED** vs DEC-017 seat-set; (3) `HoldType` enum = **6** vs DEC-008 = **8** (RM-04).
- **Floor gaps (do NOT defer):** GDPR erasure/anonymisation absent (K J-9/9a/FSM-16; no Address entity — RM-01); enhanced-KYC €10k/€50k threshold absent (seam columns only — RM-02).

## Open Patterns
- **Escalation-asymmetry (memory `spec-divergence-from-cmless-documentation`) confirmed live:** our frozen handoff carried the wrong membership flow; our findings + canon's DEC-008..023 never crossed. This validation IS the corrections-inbox — deltas → local ADRs, genuine gaps → c-mless issues.
- **Divergent module maturity:** Catalog enforces SoD (self-approval blocked, console-tested); Parties does NOT (RM-08).
- **DemoSeeder now self-provisions** (chains RoleSeeder + OperatorDemoSeeder, production-guarded, resets event/audit log): one `db:seed --class=…\DemoSeeder` yields roles + 3 operator logins + full demo data + the real-lineage SoD fixture. **Caveat: SQLite-only** (`reset()` TRUNCATE rejected by PG on FK-referenced tables) — **Tracker §7 F1**; **F2** = prod operator-mgmt missing → SoD unsatisfiable in prod. **Convention: log any incidental discovery in Remediation_Tracker §7 — never drop it.**
