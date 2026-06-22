---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-kyc-sanctions` — ralph loop RUNNING; task 2.3 done, 6/13).** Appended five `it()` blocks to `CustomerKycSanctionsConsoleTest` (test-only — verbs shipped 2.1): (1–3) the **reject FLOOR** per KYC verb (design D4), each parametrised over its OUT-of-from-states, proving BOTH halves — surface HIDES it (`assertActionHidden('verb')`) AND the domain rejects an out-of-band call (`expect(fn () => app(Action::class)->handle($id))->toThrow(IllegalKycTransition::class)` — imported freely), `kyc_status`→`toBe($from)` + `DomainEvent::count()===0` (bare factory, no baseline); coverage `requireKyc`∈{pending,verified,rejected}, `recordKycVerified`+`recordKycRejected`∈{not_required,verified,rejected,NULL}; NOT `assertNotified(action_failed)` (a hidden verb can't raise it). (4) **No-waive** (D8): `assertActionDoesNotExist('waiveKyc')`+`('waive')`. (5) **KYC↔sanctions independence** (D7): `active`+`sanctions_status=passed` Customer through a console require→verify cycle (two `Livewire::test` mounts) → `verified`+`active`, but `sanctions_status` STILL `passed`, `last_screening_at` NULL, zero screening events (`where('name','like','Customer%creening%')->count()===0`).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session): full suite 1488/1488 (8137 assn, exit 0)** — SQLite (was 1475/8063; +13 tests / +74 assn). `--filter=CustomerKycSanctionsConsoleTest` 26/26 (149 assn, was 13/75). PHPStan max 0 errors; `pint --test` clean; `openspec validate --strict` valid. Diff = `CustomerKycSanctionsConsoleTest.php` only (test-only); no production source, no `spec/`/`openspec/specs/`/`tests/Architecture/`, no migration, no composer dep. `ModuleBoundariesTest` UNCHANGED (no operand-enum import yet — that's 3.x).
- Full suite OOMs at PHP default 128 MB in result *parsing* (not a failure) — run pest with `php -d memory_limit=-1`. `--filter` + phpstan run fine at default. PG17 ritual is task 4.2 (not run this slice).

## Active Change & Next Task
- **`operator-console-parties-kyc-sanctions` — APPROVED, IN PROGRESS (6/13).** Delta on `operator-console`: 2 ADDED (KYC; sanctions) + 2 MODIFIED Customer reqs; 13 tasks / 5 groups. Group 1 (prep) + group 2 (KYC verbs 2.1/2.2/2.3) DONE.
- **Next task 3.1:** the sanctions form — a `recordScreeningAction(): Action` PRIVATE method on `ViewCustomer` (bespoke, the `placeHoldAction()` precedent, NOT `lifecycleAction`), appended to `getHeaderActions()`. Form: `verdict` Select over `SanctionsStatus::cases()` (value→value); `trigger_source` Select with RECORD-DEPENDENT options via `->options(fn () => $this->screeningSourceOptions($this->recordOf(Customer::class,$this->getRecord())))` → `['compliance_ad_hoc'=>…]`, prepending `'onboarding'=>…` iff `$c->last_screening_at === null` (design D6). Import OPERAND enums `Parties\Enums\{SanctionsStatus,ScreeningTriggerSource}` in PRODUCTION (the carve-out — `ModuleBoundariesTest` UNCHANGED); `KycStatus` NEVER imported. Labels `fields.screening_verdict`/`fields.screening_source`. Then 3.2 (write-through), 3.3 (onboarding-first floor), group 4 (PG17 chain), 5 (gates+memory).
- After all 13: review/merge → semantic-verify (GUIDE §2.7) → `openspec archive`.

## Blockers & Decisions Needed
- **None blocking task 3.1.** No open-ADR gate crossed (operator auth shipped; verbs invoke synchronous domain Actions).
- **Holds push gate STILL pending** (separate change) — origin/main push of the Holds archive+merge commits + `git branch -d ralph/operator-console-parties-holds` await Giovanni's go (classifier-gated). Local merge+archive already done.

## Open Patterns
- **`assertActionDoesNotExist` (verb NOT registered) ≠ `assertActionHidden` (registered, `->visible()` false):** former for an ABSENT verb (no-waive D8), latter for a gated verb out of from-state. Both in Filament 5.6.7 `TestsActions`.
- **Reject-floor `toThrow` under `DatabaseMigrations`:** the Action's `DB::transaction` genuinely rolls back (no outer test transaction), so `kyc_status` + event log are demonstrably unchanged after the throw. `IllegalKycTransition`: `cannotRequire(KycStatus)` non-nullable, `cannotVerify`/`cannotReject(?KycStatus)` nullable (`unset` token).
- **Event-envelope idiom** (pinned by `CustomerHoldsConsoleTest`): `Hold.scope_id`→`toBe(int)`, `DomainEvent.entity_id`→`toBe((string)…)`, `actor_id`→`toEqual` (loose, PG bigint-as-string). `entity_type`: HoldPlaced/Lifted→`Hold`; Suspended/Reactivated→`Customer`.
- **kyc-sanctions landmines:** KYC verbs event-silent (assert coupled Hold/status events, NEVER `CustomerKyc%` — D7); `KycStatus`=state enum (cast `->value`, never imported), `SanctionsStatus`/`ScreeningTriggerSource`=operand (imported 3.x — carve-out); chain-test (4.1) asserts exactly 5 events (`toEqualCanonicalizing`+`toHaveCount(5)`).
- **Form-less visibility-gated verb** + **i18n front-load** + **page header-action visibility-test API**: all consolidated in this change's `progress.md ## Codebase Patterns`.
