---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-01
---

# Hot Cache

## Last Updated
**2026-07-01 — Module 0 & K validation vs Paolo's "validate & close a module" asks (underwater).** Produced Paolo-style verdict reports in **`docs/validation/`** (`README.md` + `Module_0_Verdict` + `Module_K_Verdict`) **+ the live remediation backlog `Remediation_Tracker.md`**. Now in the **compliance-remediation phase** — Giovanni wants to arrive at Paolo more compliance-ready via a couple of fix rounds. Driven by Paolo Alfieri's 2026-07-01 mail to Taha (Giovanni cc'd). **Uncommitted** (push gate — ask Giovanni).

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1753/1753**. Module-scoped: Catalog **388**, Parties **517 domain + 518 console** (the 5 console "failures" = i18n-scanner subset-isolation artifact; pass co-loaded, 327/327).

## Active Change & Next Task
- No openspec change open. `docs/validation/` answers Paolo's 3 asks applied to us: verdict report (#1), env-readiness (#2), canon delta (#3).
- **Scoreboard vs local baseline:** Module 0 = 67 Pass / 10 Partial / 7 Fail / 16 Deferred (100). Module K = 77 / 26 / 19 / 8 (130). ~70% Pass on in-scope criteria.
- **Compliance-remediation IN PROGRESS → the live backlog is [`docs/validation/Remediation_Tracker.md`](docs/validation/Remediation_Tracker.md)** (25 items RM-01..25, Round 1/2 plan, per-item status). **Read it before touching any Module 0/K fix**, and update it + this line as items complete. **Now/Next:** nothing started; next = **RM-07** (seed ≥2 operators) → RM-04 (Hold 6→8) → RM-09 (reconcile erasure ADR).

## Blockers & Decisions Needed
- **Canon drift is now DEC-007 → DEC-023** (16 new), heaviest on Module K. Three headline divergences **confirmed against code**: (1) membership flow — we built the "approved-but-unpaid" flow **DEC-016 declared WRONG** (distinct `Approved` state + separate `ActivateProfile`); (2) capacity ships **UNCAPPED** vs DEC-017 seat-set = `Active`+`Suspended`; (3) `HoldType` enum = **6** vs DEC-008 = **8**.
- **Floor gaps (should NOT be deferred):** GDPR erasure/anonymisation entirely absent (Module K J-9/9a/FSM-16; no Address entity); enhanced-KYC €10k/€50k threshold absent (seam columns only).
- Env "not ready as asked": only **1 operator** seeded, no supported 2nd; capacity + anonymisation are unbuilt features, not seed gaps.

## Open Patterns
- **Escalation-asymmetry (memory `spec-divergence-from-cmless-documentation`) confirmed live:** our frozen handoff carried the wrong membership flow; our findings + canon's DEC-008..023 never crossed. This validation IS the corrections-inbox — route deltas → local ADRs, genuine gaps → c-mless issues.
- **Divergent module maturity:** Catalog enforces SoD (self-approval blocked, console-tested — passes Paolo's check); Parties does NOT (Producer onboarding + membership approval are single-actor).
- DemoSeeder still NOT chained into `DatabaseSeeder` (needs `db:seed --class=Database\Seeders\DemoSeeder`); seeds **no operators** and bypasses domain actions (direct `create()`).
