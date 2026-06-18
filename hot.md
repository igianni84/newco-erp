---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph — `parties-holds` iteration 9/30, task 6.1 DONE).** TEST-ONLY task pinning the unified Hold registry contract + the demand-side scope guard. NEW `tests/Feature/Modules/Parties/HoldRegistryTest.php` (4 `it` → 9 cases): (1) BR-K-Hold-1 multiple concurrent active Holds on one Customer scope (`kyc` via `RequireKyc` + `admin` via `PlaceHold`, both `active`; lift the `admin` → `kyc` remains); (2) six `HoldType`s each placeable via the manual operator path (AC-K-MVP-2); (3) all three `HoldScope`s placeable (realistic Customer/Account/Profile entities); (4) the SCOPE GUARD — `RequireKyc` + `PlaceHold`×3 + `LiftHold`×1 leave every scope entity in its birth state (`pending`/`active`/`applied`), the four demand-side status names absent, only Hold events recorded (`whereNotIn` guard). No production code, no migration, no `lang/`, no protected files, no composer drift; `SpineCreationChainTest` unamended.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 781/781 SQLite** (3664 assertions, +9 from 772). PHPStan max 0 · Pint clean · `openspec validate parties-holds --strict` valid.
- **PG17 verified** (docker `postgres:17`:55432): `tests/Feature/Modules/Parties` + `tests/Architecture` **211/211** (1394 assertions, +9). The 9 HoldRegistryTest cases ARE the PG proof — multi-Hold/active-only WHERE, polymorphic `scope_id` across all three scope_types, enum-value WHERE, the `whereNotIn` event-name guard, the Customer/Account/Profile status re-reads. Container torn down.

## Active Change & Next Task
- **`parties-holds` — IN PROGRESS (9/11 done).** Phase 1 + 2.1 + Phase 3 + 4.1 + 5.1 + 6.1 COMPLETE. Remaining: 6.2, 6.3.
- **Next task: 6.2** — Docs (NO code, NO test). Extend `CONTEXT.md` with the resolved Hold terms (six `HoldType`s; three `HoldScope`s + Customer→Profile cascade / Profile isolation; per-type lift discipline w/ ADR pointer; the `(sanctions_status, active-Hold-list)` read tuple; `reason` controlled business value, system holds null-reason) AND a Parties Hold contract note (the two event payloads `CustomerHoldPlaced`/`CustomerHoldLifted` PII-free; the `PartyComplianceStatusReader` contract; the deferred seams — downstream enforcement → S/C/E; `payment`/`fraud`/`compliance`/`credit` auto-triggers + finance subtypes → Module E; Hold→suspension → `parties-membership-lifecycle`; Hold expiry; GDPR×Hold; Filament console → `parties-operator-console`; **Account-scope placeable but NOT cascaded by the read-API** — design L6 risk note). Verbatim spec anchors (§ 4.8 / § 4.8.1 / § 15.1 / DEC-160 / DEC-181); cross-ref ADR `2026-06-18-hold-lift-discipline-per-type.md`. Verify by terminology re-read + `openspec validate`.
- After 6.2: 6.3 full Hold-chain feature test + entire-Parties-suite cross-engine PG17 close → `<promise>CHANGE_COMPLETE</promise>`.

## Blockers & Decisions Needed
- None. Root `CLAUDE.md` Invariant #7 reword (per-type lift) is the human's call at the gate (Protected file); ADR `2026-06-18-hold-lift-discipline-per-type.md` governs. No open ADR gate stepped.

## Open Patterns
- **Demand-side status events asserted absent BY STRING** (no `::NAME` — those classes are deferred). Never `%Activated%` to guard the demand side — the supply-side `ProducerActivated`/`ProducerAgreementActivated` exist and are legit; assert the exact demand-side names. Non-vacuous "only Hold events" guard = `whereNotIn([CustomerHoldPlaced::NAME, CustomerHoldLifted::NAME])->count() === 0` paired with positive placed/lifted counts (Parties factories are pure fixtures — zero emit).
- `Collection::map(fn (Hold): string)->all()` is `array<int, string>` (declare that — no `array_values`) when the consumer is order-insensitive `toEqualCanonicalizing`; reserve `array_values($coll->all())` for an actual `list<>` return.
- **Read contract = interface + DTO + `bind`-ed `Reads\` impl (5.1).** `Contracts\*` is the module public surface (a DTO-class is allowed there); `Reads\` is internal. Cascade-at-read OR-group; bare-`Builder` nested-where closures; DTO = plain `class` + promoted `public readonly` (Money idiom). PHPStan max rejects two chained `->not->…` — re-anchor with `->and($x)->not->…` (lessons.md).
- **A handoff's "affected files" list is a HINT — grep the blast radius** (carries from 4.1).
