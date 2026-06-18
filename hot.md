---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph — `parties-holds` iteration 3/30, task 1.3 DONE).** Added the lift-discipline exception `App\Modules\Parties\Exceptions\IllegalHoldLift extends RuntimeException` with two named factories mirroring `IllegalKycTransition`: `::autoManaged(HoldType)` (operator-lift of a `kyc`/`payment` Hold → `parties.hold.cannot_lift_auto_managed`, `:type`) and `::notActive(HoldStatus)` (lifting an already-`lifted` Hold → `parties.hold.cannot_lift_not_active`, `:state`). Both `(string) __()`-coerce; token is `$enum->value` (PII-free). Added the 8th `hold` group to `lang/en/parties.php` (7 pre-existing groups untouched). New `HoldExceptionTest` (6 tests). **No DB** task.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 738/738 SQLite** (3425 assertions) — +6 from 732 (the new `HoldExceptionTest`). PHPStan max 0 · Pint clean · `openspec validate parties-holds --strict` valid. Composer untouched; no protected files; no new migration (no PG17 run needed this task).

## Active Change & Next Task
- **`parties-holds` — IN PROGRESS (3/11 tasks done).** Phase 1 (Foundations) COMPLETE: enums (1.1), `parties_holds` table+model+factory (1.2, PG17-proven), `IllegalHoldLift` (1.3).
- **Next task: 2.1** — the two Hold events: `App\Modules\Parties\Events\CustomerHoldPlaced` + `CustomerHoldLifted`, each a `final class` with untyped `const NAME` (verbatim § 15.1), `const ENTITY_TYPE = 'Hold'`, static `payload(Hold): array` → Placed `{hold_id, hold_type, scope_type, scope_id, reason}` / Lifted `{…, lift_reason}`. Read nullable enum `@property` via bare `?->value` (never combine `?->` with `?? fallback` — trips `nullsafe.neverNull`). **Mirror `CustomerOnboardingScreeningPassed`** (single source of truth for name/entityType/payload). No DB — test `tests/Unit/Modules/Parties/Events/HoldEventsTest.php` (assert NAMEs byte-for-byte via concrete class ref, ENTITY_TYPE, payload keys + no PII).
- Build position: end of Phase 1; Mod K ~75-80% once this slice lands. After 2.1: Actions (3.1 PlaceHold / 3.2 LiftHold), KYC coupling (4.1), read-API (5.1), registry/docs/PG-close (6.x).

## Blockers & Decisions Needed
- None. ADR `2026-06-18-hold-lift-discipline-per-type.md` is the standing authority for the per-type lift discipline (root `CLAUDE.md` Invariant #7 kept as-is — Protected file; reword is the human's call, surfaced at gate). No open ADR gate stepped (events inline; no queue/object-storage/payment-provider/dependency).

## Open Patterns
- **Pint `{@see}` forward-ref trap (re-confirmed 1.3):** cite a not-yet-built class (`LiftHold`, task 3.2) in PLAIN backticks; `{@see <Sibling>}` is safe ONLY for a same-namespace class (Pint adds no `use`). The `knowledge/laravel/rules.md` rule.
- **i18n interpolation-proof copy (1.3):** keep the token's literal VALUE out of the template (`cannot_lift_not_active` says "lifts only from active", no literal `lifted`) so `toContain('<token>')` proves interpolation. A parametrized resolution test can pass BOTH placeholder names at once (Laravel ignores the unreferenced one).
- **Evented-Action idiom (upcoming 3.x):** PlaceHold/LiftHold inject `DomainEventRecorder` + `ActorContext`; `record(NAME, Module::Parties->value, role, id, ENTITY_TYPE, (string)$hold->id, payload)` INSIDE the `DB::transaction`. `LiftHold` re-reads `lockForUpdate`, guards `status` + `autoLiftable()`. `autoLiftable()` is the single source of truth for the lift guard (3.2) + the `RecordKycVerified` system-lift (4.1).
