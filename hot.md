---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph — `parties-holds` iteration 4/30, task 2.1 DONE).** Added the two § 15.1 Hold events under `app/Modules/Parties/Events/`: `CustomerHoldPlaced` + `CustomerHoldLifted`, each a `final class` mirroring `CustomerOnboardingScreeningPassed` — untyped `const NAME` (verbatim, byte-for-byte), `const ENTITY_TYPE = 'Hold'`, static `payload(Hold): array`. Placed `{hold_id, hold_type, scope_type, scope_id, reason}` / Lifted `{…, lift_reason}`; PII-free. `hold_type`/`scope_type` are NON-nullable enum props → plain `->value` (a `?->value` trips `nullsafe.neverNull`). New `HoldEventsTest` (6 tests). **No DB** task.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 744/744 SQLite** (3446 assertions) — +6 from 738 (the new `HoldEventsTest`). PHPStan max 0 · Pint clean · `openspec validate parties-holds --strict` valid. Composer untouched; no protected files; no migration (no PG17 run needed this task).

## Active Change & Next Task
- **`parties-holds` — IN PROGRESS (4/11 tasks done).** Phase 1 (Foundations: enums 1.1, table+model+factory 1.2, `IllegalHoldLift` 1.3) + Phase 2 events (2.1) COMPLETE.
- **Next task: 3.1** — `PlaceHold` Action (`app/Modules/Parties/Actions/PlaceHold.php`): `handle(HoldType $type, HoldScope $scope, int $scopeId, ?string $reason = null): Hold`. Inject `DomainEventRecorder` + `ActorContext` (the `RecordCustomerScreening` shape). ONE `DB::transaction`: create the Hold (`status = active`, `placed_actor_role`/`placed_actor_id` from `ActorContext`, `reason`), then `record(CustomerHoldPlaced::NAME, Module::Parties->value, $actor->role(), $actor->actorId(), CustomerHoldPlaced::ENTITY_TYPE, (string) $hold->id, CustomerHoldPlaced::payload($hold))` INSIDE the same transaction (recorder's open-tx guard → atomic). Manual operator path AND the path `RequireKyc` reuses for the auto `kyc` Hold (4.1). **DB-touching → MUST verify on PG17.** Test `tests/Feature/Modules/Parties/HoldLifecycleTest.php` (`RefreshDatabase`): assert persisted fields + exactly one `CustomerHoldPlaced` (`where('name','CustomerHoldPlaced')->count()===1`, `entity_type==='Hold'`, payload keys) + default `ActorRole::System`.
- After 3.1: 3.2 LiftHold (per-type lift guard via `autoLiftable()` + `IllegalHoldLift`), 4.1 KYC coupling (the one MODIFIED behaviour — flip the shipped "no Hold" test), 5.1 read-API, 6.x registry/docs/PG-close. Mod K ~80% once 3.x lands.

## Blockers & Decisions Needed
- None. ADR `2026-06-18-hold-lift-discipline-per-type.md` is the standing authority for the per-type lift discipline (root `CLAUDE.md` Invariant #7 reword is the human's call at the gate — Protected file). No open ADR gate stepped (events inline; no queue/object-storage/payment-provider/dependency).

## Open Patterns
- **`->value` vs `?->value` is `@property`-nullability-driven (2.1):** non-nullable enum prop → plain `->value`; nullable (`...|null`) → bare `?->value` (never with `?? fallback`). Check the model's `@property` line before choosing; the "read nullable enum via `?->value`" note is conditional, not blanket. `reason`/`lift_reason` are nullable STRINGS → emit directly, no `->value`.
- **Pint `{@see}` forward-ref trap (re-confirmed 2.1):** same-namespace class (`{@see CustomerHoldPlaced}` from the Lifted event) is safe (no `use` added); cross-namespace not-yet-built classes (`PlaceHold`/`LiftHold`/`RequireKyc`/`RecordKycVerified`, Actions ns) stay in PLAIN backticks. Eyeball the post-Pint `use` block. The `knowledge/laravel/rules.md` rule.
- **Evented-Action idiom (3.1/3.2):** inject `DomainEventRecorder` + `ActorContext`; `record(NAME, Module::Parties->value, role, id, ENTITY_TYPE, (string)$hold->id, payload)` INSIDE the `DB::transaction`. `LiftHold` re-reads `lockForUpdate`, guards `status` + `autoLiftable()` (single source of truth for the lift guard 3.2 + the `RecordKycVerified` system-lift 4.1). **Add `PlaceHold`/`LiftHold` to `SupplyLifecycleChainTest`'s transition-Action whitelist or the suite goes RED.**
