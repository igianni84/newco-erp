---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph — `parties-holds` iteration 6/30, task 3.2 DONE).** Added the lift counterpart `app/Modules/Parties/Actions/LiftHold.php` — `handle(int $holdId, ?string $reason = null): Hold`. Same evented-Action shape as `PlaceHold` (inject `DomainEventRecorder` + `ActorContext`). ONE `DB::transaction`: `lockForUpdate` re-read → **status guard BEFORE type guard** (`status !== Active` → `IllegalHoldLift::notActive`; `hold_type->autoLiftable()` → `IllegalHoldLift::autoManaged`) → resolve actor ONCE + one `CarbonImmutable::now()` → `update(status=Lifted, lifted_actor_*, lifted_at, lift_reason)` → `record(CustomerHoldLifted::NAME, …)` in-tx. ROOT event. Extended `HoldLifecycleTest` (+7 cases) + appended `'LiftHold'` to `SupplyLifecycleChainTest` `$holdTransitions`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 753/753 SQLite** (3548 assertions, +7 from 746). PHPStan max 0 · Pint clean · `openspec validate parties-holds --strict` valid. Composer untouched; no protected files; no migration this task.
- **PG17 verified** (docker `postgres:17`:55432): `tests/Feature/Modules/Parties` + `tests/Architecture` **183/183** (+7). Hold row round-trips `active → lifted` with typed casts (`lifted_at` timestamptz→immutable_datetime, `lifted_actor_role` ActorRole), `CustomerHoldLifted` records PII-free by-key — all on PG. Container torn down.

## Active Change & Next Task
- **`parties-holds` — IN PROGRESS (6/11 done).** Phase 1 (1.1/1.2/1.3) + 2.1 events + Phase 3 Actions (3.1 PlaceHold, 3.2 LiftHold) COMPLETE.
- **Next task: 4.1** — KYC↔Hold coupling (the ONE MODIFIED behaviour; design L7). Modify `app/Modules/Parties/Actions/RequireKyc.php`: inject recorder+actor (or `PlaceHold`), after the `→ pending` write place a Customer-scope `kyc` Hold (`reason = null` — L5) in the SAME transaction. Modify `app/Modules/Parties/Actions/RecordKycVerified.php` (**currently injects NOTHING — eventless**): inject recorder+actor, after the `→ verified` write lift the Customer's active `kyc` Hold(s) via a **SYSTEM lift path** (set `lifted`, record `CustomerHoldLifted` directly) in the SAME transaction. **It CANNOT reuse `LiftHold` — the operator path REJECTS `kyc` via `autoManaged`**; design L2 wants a separate within-module system-lift. `RecordKycRejected` UNCHANGED (Hold remains — § 9.1). Update both action docblocks (the "NO Hold is placed/lifted … parties-holds owns it" notes flip to describe the coupling). **FLIP** `CustomerKycLifecycleTest` "no Hold" (`where('name','like','%Hold%')->count()===0`) AND `ComplianceIndependenceTest` `%Hold%`-count-0 assertions → assert `CustomerHoldPlaced` on require + `CustomerHoldLifted` on verify; add a require→reject case (Hold stays `active`, 0 lift event). **DB-touching → verify on PG17.**
- After 4.1: 5.1 read-API (Contracts + DTO + bound reader), 6.x registry/docs/PG-close.

## Blockers & Decisions Needed
- None. ADR `2026-06-18-hold-lift-discipline-per-type.md` governs the per-type lift discipline (root `CLAUDE.md` Invariant #7 reword is the human's call at the gate — Protected). No open ADR gate stepped.
- **Noted (not blocking):** PlaceHold does not resolve/validate the scope (design L1 mentions it; task 3.1 acceptance + the exception inventory scope it to create+record) — flagged for the gate; the one live caller (`RequireKyc`, 4.1) places on an already-locked Customer.

## Open Patterns
- **Guard ordering: status BEFORE type (3.2).** `LiftHold` throws `::notActive` before consulting `autoLiftable()` — an out-of-state lift is the more fundamental rejection. The two rejection tests never collide (auto-managed uses `active` kyc/payment; double-lift uses already-`lifted` admin).
- **`toThrow(Exception::class, $token)` proves i18n interpolation (3.2).** The `:type`/`:state` tokens are absent from the lang templates (1.3), so a substring assertion on the token value can only pass if interpolation fired. `lifts`/`lift` ≠ substring `lifted` (verified).
- **Resolve the ActorContext actor ONCE per evented-Action** (3.1/3.2). One read stamps both the row's actor columns AND the envelope so they can't disagree. `LiftHold` resolves AFTER the guards (lift path only). The payload (`CustomerHoldLifted::payload`) is built AFTER `update()` → reads the just-written `lift_reason` (Eloquent mutates in place, no re-read).
- **`->value` vs `?->value` is `@property`-nullability-driven (2.1):** non-nullable enum prop → plain `->value`; nullable → bare `?->value`. **Pint `{@see}` forward-ref trap:** not-yet-built cross-namespace classes stay in PLAIN backticks; existing/same-namespace `{@see}` fine (Pint auto-imports it) — eyeball the post-Pint `use` block.
