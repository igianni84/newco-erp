---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph — `parties-holds` iteration 10/30, task 6.2 DONE).** DOCS-ONLY — extended `CONTEXT.md` with the resolved Hold vocabulary (5 edits): (1) Parties intro lists `parties-holds` as Hold-registry implementer; (2) the stale `**Hold**` seed REPLACED by a six-term cluster under `## Compliance & Finance` (**Hold** · **Hold type** verbatim `admin/kyc/payment/fraud/compliance/credit` · **Hold scope** `customer/account/profile` + Customer→Profile cascade · **Hold-lift discipline** `autoLiftable()` kyc+payment, refines CLAUDE.md Inv#7→ADR · **Hold `reason`** system-null · **Compliance read-API** tuple); (3) `**KYC lifecycle**` gains the now-landed kyc-Hold coupling (KYC still records no *KYC* event); (4) kyc-coupling seam marked "(**now landed**)"; (5) NEW `### Parties Hold events — payload contract` subsection (2 events + PII-free payload table, `PartyComplianceStatusReader` read-contract note DEC-181, six deferred seams). Names verified against source. No PHP/migration/lang/composer/protected change.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 781/781 SQLite** (3664 assertions — UNCHANGED from 6.1; docs-only). PHPStan max 0 · Pint --test clean · `openspec validate parties-holds --strict` valid.
- **No PG17 run this task** (no DB touched — 6.2 is the one slice task with no database). 6.1's PG17 proof stands: `tests/Feature/Modules/Parties` + `tests/Architecture` 211/211 on docker `postgres:17`.

## Active Change & Next Task
- **`parties-holds` — IN PROGRESS (10/11 done).** Phase 1 + 2.1 + Phase 3 + 4.1 + 5.1 + 6.1 + 6.2 COMPLETE. Only **6.3** remains.
- **Next task: 6.3 — Full Hold chain + PG17 close (LAST task → CHANGE_COMPLETE).** New `tests/Feature/Modules/Parties/HoldChainTest.php` end-to-end: create Customer(+Profiles) → `RequireKyc` (kyc Hold auto-placed + `CustomerHoldPlaced`) → `PlaceHold` admin → read-API `isClear()` false + Profile cascade → `LiftHold` admin (`CustomerHoldLifted`; operator-lift of the kyc Hold REJECTED) → `RecordKycVerified` (kyc auto-lifts) → read-API clear once sanctions `passed`. Assert `domain_events` carries ONLY the expected `CustomerHold*` (exact counts, zero invented/zero KYC names), `isClear()` flips, NO demand-side status transition. Then ENTIRE Parties suite + 2 arch tests on PG17 (docker `postgres:17`:55432), record in `progress.md`, final-pass re-verify acceptance → `<promise>CHANGE_COMPLETE</promise>`.

## Blockers & Decisions Needed
- None. Root `CLAUDE.md` Invariant #7 reword (per-type lift) is the human's call at the gate (Protected); ADR `2026-06-18-hold-lift-discipline-per-type.md` governs. No open ADR gate stepped.

## Open Patterns
- **CONTEXT.md docs convention (in progress.md Codebase Patterns).** Glossary TERMS group by domain theme (Hold cluster → `## Compliance & Finance`, not `## Parties`); EVENT-PAYLOAD CONTRACT → a `### <Module> … events — payload contract` subsection under the owning `##` (table keys verbatim from the event class `payload()`); a CLOSED seam gets `(**now landed**)`, not deletion; REFINE (never silently contradict) a Protected-file line, naming the ADR. Docs task → no DB → no PG17.
- **Carryover (live for 6.3).** Demand-side status events absent BY STRING (`CustomerActivated`/`ProfileActivated`/`OriginatingClubLocked`/`CustomerSegmentChanged` — deferred, no `::NAME`); never `%Activated%` (catches legit `ProducerActivated`). "Only Hold events" guard = `whereNotIn([CustomerHoldPlaced::NAME, CustomerHoldLifted::NAME])->count()===0` + positive placed/lifted counts. `%Kyc%` stays 0 (coupled events are `CustomerHold*`). `->sole()` not `->first()->prop` (PHPStan). Full suite: `php -d memory_limit=512M vendor/bin/pest`.
