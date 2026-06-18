---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph — `parties-holds` iteration 5/30, task 3.1 DONE).** Added the first Hold lifecycle Action `app/Modules/Parties/Actions/PlaceHold.php` — `handle(HoldType, HoldScope, int $scopeId, ?string $reason = null): Hold`. Mirrors `RecordCustomerScreening`: injects `DomainEventRecorder` + `ActorContext`; ONE `DB::transaction` → resolve actor ONCE into locals (`$actorRole`/`$actorId` stamp BOTH the `placed_actor_*` columns AND the envelope) → `Hold::create([… status = HoldStatus::Active …])` → `record(CustomerHoldPlaced::NAME, Module::Parties->value, …, (string)$hold->id, payload)` in-tx (open-tx guard → atomic). ROOT event. Manual operator path + the path `RequireKyc` reuses for the auto `kyc` Hold (4.1). New `HoldLifecycleTest` (2 tests). Whitelisted `PlaceHold` in `SupplyLifecycleChainTest` (`$holdTransitions`).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 746/746 SQLite** (3485 assertions, +2 from 744). PHPStan max 0 · Pint clean · `openspec validate parties-holds --strict` valid. Composer untouched; no protected files; no migration this task.
- **PG17 verified** (docker `postgres:17`:55432): `tests/Feature/Modules/Parties` + `tests/Architecture` **176/176** (+2). Hold row persists + re-reads with typed casts, `CustomerHoldPlaced` records PII-free by-key, System actor resolves — all on PG.

## Active Change & Next Task
- **`parties-holds` — IN PROGRESS (5/11 tasks done).** Phase 1 (1.1/1.2/1.3) + Phase 2 events (2.1) + Phase 3 first Action (3.1 PlaceHold) COMPLETE.
- **Next task: 3.2** — `LiftHold` Action (`app/Modules/Parties/Actions/LiftHold.php`): `handle(int $holdId, ?string $reason = null): Hold`. Same evented-Action shape (inject recorder+actor; resolve actor ONCE). ONE `DB::transaction`: `Hold::query()->whereKey($holdId)->lockForUpdate()->firstOrFail()` re-read; if `status !== HoldStatus::Active` throw `IllegalHoldLift::notActive($hold->status)`; if `$hold->hold_type->autoLiftable()` throw `IllegalHoldLift::autoManaged($hold->hold_type)` (operator path forbids `kyc`/`payment`); else `update(['status'=>Lifted, 'lifted_actor_role'=>$actorRole, 'lifted_actor_id'=>$actorId, 'lifted_at'=>CarbonImmutable::now(), 'lift_reason'=>$reason])` + `record(CustomerHoldLifted::NAME, …)` in-tx. **DB-touching → MUST verify on PG17.** Extend `HoldLifecycleTest`: place+lift `admin` → `Lifted` + one `CustomerHoldLifted`; parametrize `{Kyc,Payment}` → throws `IllegalHoldLift`, stays `active`, zero lift event; double-lift `admin` → `::notActive`. **Append `'LiftHold'` to `$holdTransitions` in `SupplyLifecycleChainTest` or RED.**
- After 3.2: 4.1 KYC coupling (the one MODIFIED behaviour — wire PlaceHold/lift into RequireKyc/RecordKycVerified; flip `CustomerKycLifecycleTest` AND `ComplianceIndependenceTest` `%Hold%` assertions), 5.1 read-API, 6.x registry/docs/PG-close.

## Blockers & Decisions Needed
- None. ADR `2026-06-18-hold-lift-discipline-per-type.md` governs the per-type lift discipline (root `CLAUDE.md` Invariant #7 reword is the human's call at the gate — Protected). No open ADR gate stepped.
- **Noted (not blocking):** design L1 says PlaceHold "resolves the scoped entity / rejects missing scope," but task 3.1's acceptance scopes it to create+record and task 1.3 made no scope-not-found exception — deferred per the task decomposition (the one live caller `RequireKyc` places on an already-locked Customer). Flagged in progress.md for the gate.

## Open Patterns
- **Resolve the ActorContext actor ONCE per evented-Action (3.1).** PlaceHold reads `role()`/`actorId()` into locals stamping both the row's `placed_actor_*` AND the envelope — one read so they never disagree (lazy per-call seam could differ under a `runAs` boundary). Set `status` explicitly on create (don't rely on the column default — the returned model must read `active` in-memory).
- **`SupplyLifecycleChainTest` is the ONLY exact-set non-Create whitelist (grep-confirmed).** `ComplianceIndependenceTest` globs Actions too but uses a forbidden-NAME negative check → needs no edit for new Hold Actions. **4.1 time-bomb:** the `%Hold%`-count-0 assertions in `ComplianceIndependenceTest` + `CustomerKycLifecycleTest` flip when 4.1 wires the coupling (3.x only defines the Actions — stays green now).
- **No PG numeric-string trap in the placement payload (3.1):** `scope_id` model-cast `'integer'` → int both engines; `hold_id`/`scope_id` jsonb values trace to the same in-memory `$hold->*` and JSON preserves type → `toBe(...)` is engine-stable. `entity_id` is `(string)$hold->id` → string column.
- **`->value` vs `?->value` is `@property`-nullability-driven (2.1):** non-nullable enum prop → plain `->value`; nullable → bare `?->value`. **Pint `{@see}` forward-ref trap:** not-yet-built cross-namespace classes (`LiftHold`) stay in PLAIN backticks; same-namespace/existing classes `{@see}` fine — eyeball the post-Pint `use` block.
