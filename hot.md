---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`operator-console-parties-membership` — group 4 GREEN, 9/16 tasks).** Shipped the demand-side membership-**status** surface as one atomic section commit: `ViewProfile::getHeaderActions()` now APPENDS three form-less verbs onto the group-3 array — `activate` (`approved → active`, success key `activated`) → `app(ActivateProfile::class)->handle(…)` **UNCAPPED** (the Hero-Package cap is a deferred Module-A seam — the Action itself ships uncapped; no cap invented, design Non-Goals); `suspend` (`active → suspended`, `suspended`) → `SuspendProfile`; `reactivate` (`suspended → active`, `reactivated`) → `ReactivateProfile`. Each `->visible(fn () => $this->stateIs('<from>'))` (REUSED the existing parametric helper — no new code). Imports added: `ActivateProfile`/`ReactivateProfile`/`SuspendProfile` (all `Parties\Actions`) — verified boundary-clean post-Pint (only {Models, Actions}). i18n: `profile.actions.{activate,suspend,reactivate}` + `notifications.{activated,suspended,reactivated}`, EN ('Activate'/…) + IT ('Attiva'/'Sospendi'/'Riattiva'; 'Adesione attivata.'/…), DEC-127 intact, IT distinct from EN.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session, group 4):** full suite **1610/1610** (8858 assertions, SQLite) · PHPStan max 0 · Pint clean · `openspec validate operator-console-parties-membership --strict` valid. New `ProfileActivationConsoleTest` 21/21 (175 assertions). Arch `NoEloquentWriteInOperatorPanelRule` + `ModuleBoundariesTest` green.
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (OOMs at 128 MB; lesson 2026-06-20). PG17 close-ritual run deferred to task 8.1.

## Active Change & Next Task
- **`operator-console-parties-membership` — groups 1–4 done; 7 tasks left.** Next: **5.1** append `lapse`/`renew`/`cancel`/`deactivate` to `ViewProfile` (reuse `stateIs()`): `lapse` iff `active` → `LapseProfile` (`ProfileExpired`); `renew` iff `lapsed` → `RenewProfile` (`ProfileRenewed`) — **design D5: a past-grace renew is UI-reachable → the domain rejects → `action_failed`, the SOLE notification reject in this slice** (test through the page: `travelTo` 31d past `lapsed_at`, assert danger `assertNotified` + state stays `lapsed` + no event); `cancel` iff `active||lapsed` → `CancelProfile` **audit-only, no event** (soft-delete, row stays queryable, AC-K-FSM-13); `deactivate` iff `active` → `DeactivateProfile` (`ProfileInactive`). **First read** `app/Modules/Parties/Actions/{LapseProfile,RenewProfile,CancelProfile,DeactivateProfile}.php` to confirm `handle()` sigs + events. Then 5.2 (i18n `actions.{lapse,renew,cancel,deactivate}` + `notifications.{lapsed,renewed,cancelled,deactivated}` EN/IT) + `ProfileLifecycleConsoleTest`.

## Blockers & Decisions Needed
- **No blocker.** Boundary seams unchanged (out of scope, design Non-Goals): `Applied→WaitingList` (no writer), activation capacity cap (Module-A seam — activation ships uncapped), `MembershipFeePaid`/renewal trigger (Module-E seam), Producer-Portal TanStack UI.

## Open Patterns
- **ViewProfile lifecycle verb = `lifecycleAction($verb,$successKey,$invoke)->visible(fn () => $this->stateIs('<from>'))`**, appended to the same `getHeaderActions()` array across groups 3–5. `stateIs()` reads `state` via the `ProfileState` cast `->value` (no `Parties\Enums` import). Group-5 `cancel` = `stateIs('active')||stateIs('lapsed')`. `Illegal*Transition`/events referenced **prose-only** (Pint `{@see}` import trap, lesson 2026-06-20).
- **Multi-from-state gated-verb test (group 4; reuse in 5/6):** one file, several verbs with DIFFERENT from-states → visibility sweep maps each state to a `?string $visibleVerb`; reject-floor builds `$fromStateOf`+`$invokeOutOfBand` closure maps, `continue`s the legal state, asserts `assertActionHidden`+`toThrow(IllegalProfileTransition)`. **PHPStan-max: literal `app(X::class)` per closure, never `app($var)`.** Hidden verb = undriveable via `callAction` → assert the **domain throw**, never `assertNotified(action_failed)` (lesson 2026-06-22; except group-5 past-grace `renew`, design D5). Suspend state-preservation (AC-K-FSM-2a): seed `ClubCredit::factory()`, assert reloaded credit still `Active` + `->remaining->equals(...)`.
- **Section = one atomic commit** (groups 1–4 precedent). No new `Parties\Actions\*` → `SupplyLifecycleChainTest` whitelist unchanged (design D8).
