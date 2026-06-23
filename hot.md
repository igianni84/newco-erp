---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`operator-console-parties-membership` — group 5 GREEN, 11/16 tasks).** Shipped the demand-side membership **lapse / renew / terminal** surface as one atomic section commit: `ViewProfile::getHeaderActions()` now APPENDS four more form-less verbs onto the group-4 array (REUSING `stateIs()`): `lapse` (`active → lapsed`, key `lapsed`) → `LapseProfile` (`ProfileExpired`); `renew` (`lapsed → active`, `renewed`) → `RenewProfile` (`ProfileRenewed`); `cancel` (`active||lapsed` via `stateIs('active') || stateIs('lapsed')`, `cancelled`) → `CancelProfile` (**AUDIT-ONLY, no event**, terminal soft-delete); `deactivate` (`active → inactive`, `deactivated`) → `DeactivateProfile` (`ProfileInactive`). Imports added (`CancelProfile`/`DeactivateProfile`/`LapseProfile`/`RenewProfile`, all `Parties\Actions`) — verified boundary-clean post-Pint (only {Models, Actions}). i18n: `profile.actions.{lapse,renew,cancel,deactivate}` + `notifications.{lapsed,renewed,cancelled,deactivated}`, EN + IT ('Fai scadere'/'Rinnova'/'Annulla'/'Disattiva'; 'Adesione scaduta.'/…). DEC-127 intact; IT distinct from EN.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session, group 5):** full suite **1634/1634** (9095 assertions, SQLite) · PHPStan max 0 · Pint clean · `openspec validate operator-console-parties-membership --strict` valid. New `ProfileLifecycleConsoleTest` 24/24 (237 assertions). Arch `NoEloquentWriteInOperatorPanelRule` + `ModuleBoundariesTest` green.
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (OOMs at 128 MB; lesson 2026-06-20). PG17 close-ritual run deferred to task 8.1.

## Active Change & Next Task
- **`operator-console-parties-membership` — groups 1–5 done; 5 tasks left.** Next: **6.1** on `CustomerResource/Pages/ViewCustomer.php` `getHeaderActions()`, append three form-less Account verbs via `lifecycleAction`, gated by the 1:1 Account's `status` (`$customer->account` non-null by co-provisioning): `suspendAccount` → `app(SuspendAccount::class)->handle($this->recordOf(Customer::class,$record)->account->id)` iff `…->account->status->value === 'active'`; `reactivateAccount` → `ReactivateAccount` iff `'suspended'`; `closeAccount` → `CloseAccount` iff status ∈ {active,suspended}. **No `ActivateAccount`** (born active). Reference `IllegalAccountTransition` in **prose only** (Pint trap). **First read** `app/Modules/Parties/Actions/{SuspendAccount,ReactivateAccount,CloseAccount}.php` to confirm `handle(int)` sigs (all audit-only → no Account events on disk). Then 6.2 (i18n under `operator_console.customer.*`: `actions.{suspend_account,reactivate_account,close_account}` + `notifications.{account_suspended,account_reactivated,account_closed}` EN/IT) + `AccountLifecycleConsoleTest` (suspend asserts orthogonality — Customer status + Profiles unchanged, AC-K-FSM-9; zero domain events).

## Blockers & Decisions Needed
- **No blocker.** Boundary seams unchanged (out of scope, design Non-Goals): `Applied→WaitingList` (no writer), activation capacity cap (Module-A seam — activation ships uncapped), `MembershipFeePaid`/renewal trigger (Module-E seam), Producer-Portal TanStack UI.

## Open Patterns
- **ViewProfile lifecycle verb = `lifecycleAction($verb,$successKey,$invoke)->visible(fn () => $this->stateIs('<from>'))`**, appended to the same `getHeaderActions()` array across groups 3–5 (9 verbs now). `stateIs()` reads `state` via the `ProfileState` cast `->value`. Two-state gate (`cancel`) = `stateIs('active') || stateIs('lapsed')`. `Illegal*Transition`/events referenced **prose-only** (Pint `{@see}` import trap).
- **OVERLAPPING-from-state test (group 5; reuse in 6):** verbs sharing a from-state → visibility sweep maps each state to an `array $visibleVerbs` (`in_array` check); reject-floor maps each verb to a `list<ProfileState>` of legal-froms (`cancel`=[Active,Lapsed]) and `continue`s when `in_array($from,$legalFroms,true)`. Hidden verb → assert domain `toThrow(IllegalProfileTransition)` + `assertActionHidden`, never `assertNotified(action_failed)` (lesson 2026-06-22). `cancel` is audit-only but STILL throws out-of-band. Terminal soft-delete (AC-K-FSM-13) = `Profile::query()->whereKey($id)->exists()` (no SoftDeletes scope).
- **D5 past-grace `renew` = SOLE UI-reachable reject:** `callAction('renew')->assertNotified(…action_failed)` + state/event unchanged. Make it past-grace by anchoring `'lapsed_at' => CarbonImmutable::now()->subDays(31)` — **NOT `$this->travelTo()`** (reds PHPStan inside a Pest closure → `TestCall::travelTo()`; lesson 2026-06-23).
- **Section = one atomic commit** (groups 1–5). No new `Parties\Actions\*` → `SupplyLifecycleChainTest` whitelist unchanged (design D8).
