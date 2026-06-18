---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph — `parties-holds` iteration 8/30, task 5.1 DONE).** Shipped the uniform Hold/sanctions read-API (design L6). NEW `app/Modules/Parties/Contracts/ComplianceStatus.php` (PII-free DTO: promoted `public readonly ?SanctionsStatus` + `list<HoldType>`, `isClear()` = sanctions `Passed` ∧ no active Hold; the `Money` value-object idiom, NEVER the `Hold` model), NEW `Contracts/PartyComplianceStatusReader.php` (interface `forCustomer(int)`/`forProfile(int)`), NEW `Reads/DatabaseComplianceStatusReader.php` (cascade-at-read in ONE OR-group query: `forProfile` = profile-scope ∪ parent-Customer-scope active Holds + parent Customer's sanctions; `forCustomer` = customer-scope only; Account NOT cascaded), bound in `PartiesServiceProvider::register` via `bind(Interface, Concrete)`. No downstream enforcement (scope guard). No migration, no `lang/`, no protected files, no composer drift.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 772/772 SQLite** (3623 assertions, +18 from 754). PHPStan max 0 · Pint clean · `openspec validate parties-holds --strict` valid.
- **PG17 verified** (docker `postgres:17`:55432): `tests/Feature/Modules/Parties` + `tests/Architecture` **202/202** (1353 assertions, +18). The ComplianceReadApiTest cases ARE the PG proof — the cascade OR-group query, enum WHERE clauses, Customer→Profile cascade + Profile isolation, `findOrFail` + `sanctions_status` cast all round-trip on PG. Container torn down.

## Active Change & Next Task
- **`parties-holds` — IN PROGRESS (8/11 done).** Phase 1 + 2.1 + Phase 3 + 4.1 + 5.1 COMPLETE. Remaining: 6.1, 6.2, 6.3.
- **Next task: 6.1** — Hold registry behaviour + scope-guard test (design L1/L3; spec — Hold Registry, MODIFIED Birth States). NEW test-only `tests/Feature/Modules/Parties/HoldRegistryTest.php` pinning: multiple concurrent active Holds on one scope (BR-K-Hold-1 — place a `kyc` via `RequireKyc` AND an `admin` via `PlaceHold` → both `active`; lift the `admin` → `kyc` remains); the six types all placeable (loop the six `HoldType` through `PlaceHold`, a row each); and the SCOPE GUARDS — placing/lifting a Hold performs NO Customer/Account/Profile **status** transition (Customer `status` stays `pending`) and records NO demand-side status event (`CustomerActivated`/`ProfileActivated`/`OriginatingClubLocked` absent in `domain_events`); `SpineCreationChainTest` stays green unamended. **DB-touching → verify on PG17.**
- After 6.1: 6.2 CONTEXT.md Hold terms + contract note (docs, no code), 6.3 full Hold-chain + cross-engine close → CHANGE_COMPLETE.

## Blockers & Decisions Needed
- None. ADR `2026-06-18-hold-lift-discipline-per-type.md` governs the per-type lift; root `CLAUDE.md` Invariant #7 reword is the human's call at the gate (Protected). No open ADR gate stepped.
- **Noted (not blocking):** Account-scope Holds are placeable but NOT cascaded by the read-API (PRD specifies no Account cascade — design L6 risk note); flag in 6.2. PlaceHold still doesn't resolve/validate the scope (design L1 risk); `payment` Holds have no live trigger this slice (auto-lift signal is Module E — deferred seam).

## Open Patterns
- **Read contract = interface + DTO + `bind`-ed `Reads\` impl (5.1).** `Contracts\*` is the module public surface (a DTO-class is allowed there); `Reads\` is internal (a new subdir is invisible to `ModuleConformanceTest`, which checks only the modules root). Cascade-at-read OR-group; `array_values($coll->all())` is what gives PHPStan a `list<>`; nested-where closures typed bare `Builder`; DTO = plain `class` + promoted `public readonly` (Money idiom), not `final readonly class`.
- **PHPStan max rejects two chained `->not->…`** — re-anchor with `->and($x)->not->…` (the `HoldLifecycleTest` idiom). lessons.md.
- **A handoff's "affected files" list is a HINT — grep the blast radius** (carries from 4.1).
