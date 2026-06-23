---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`operator-console-parties-membership` — group 3 GREEN, 7/16 tasks).** Shipped the demand-side **membership-approval** surface as one atomic section commit: **`ViewProfile::getHeaderActions()`** now returns the two form-less verbs (replacing the empty scaffold) — `approve` (success key `approved`) → `app(ApproveProfile::class)->handle(…)` and `decline` (success key `declined`) → `app(DeclineProfile::class)->handle(…)`, each `->visible()`-gated to `applied` via a new parametric **`private stateIs(string): bool`** helper (reads `state` through the `ProfileState` cast `->value` — NO `Parties\Enums` import, design D2; mirrors `ViewCustomer::kycPending()`). Imports added: `ApproveProfile`/`DeclineProfile`/`Profile`/`Model` (all {Models, Actions}); `IllegalProfileTransition` in **prose only** (Pint trap dodged, imports verified clean). i18n: `profile.actions.{approve,decline}` + a new `notifications.{approved,declined,action_failed}` block, EN ('Approve'/'Decline'; 'Membership approved.'/…) + IT ('Approva'/'Rifiuta'; 'Adesione approvata.'/…), DEC-127 intact, IT distinct from EN.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session, group 3):** full suite **1589/1589** (8683 assertions, SQLite) · PHPStan max 0 · Pint clean · `openspec validate operator-console-parties-membership --strict` valid. New `ProfileApprovalConsoleTest` 20/20 (133 assertions). Arch `NoEloquentWriteInOperatorPanelRule` + `ModuleBoundariesTest` green.
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (OOMs at 128 MB; lesson 2026-06-20). PG17 close-ritual run deferred to task 8.1.

## Active Change & Next Task
- **`operator-console-parties-membership` — groups 1–3 done; 9 tasks left.** Next: **4.1** append `activate`/`suspend`/`reactivate` to `ViewProfile` (reuse `stateIs()`): `activate` visible iff `approved` → `app(ActivateProfile::class)->handle(…)` **UNCAPPED** (Hero-Package cap is a deferred Module-A seam, design Non-Goals — invent no cap); `suspend` iff `active`; `reactivate` iff `suspended`. **First read** `app/Modules/Parties/Actions/{ActivateProfile,SuspendProfile,ReactivateProfile}.php` to confirm `handle()` sigs + events (`ProfileActivated`/`ProfileSuspended`/`ProfileReactivated`). Then 4.2 (`actions.{activate,suspend,reactivate}` + `notifications.{activated,suspended,reactivated}` EN/IT). 4.x test: on suspend, assert a co-existing `activeClubCredit` (ClubCredit factory) is **unchanged** (state-preservation, AC-K-FSM-2a).

## Blockers & Decisions Needed
- **No blocker.** Boundary seams unchanged (out of scope, design Non-Goals): `Applied→WaitingList` (no writer), activation capacity cap (Module-A seam — uncapped, task 4.1), `MembershipFeePaid` (Module-E seam), Producer-Portal TanStack UI.

## Open Patterns
- **ViewProfile lifecycle verb = `lifecycleAction($verb,$successKey,$invoke)->visible(fn () => $this->stateIs('<from>'))`**, appended to the same `getHeaderActions()` array across groups 3–5. `$invoke = fn (Model $r, string $notes) => app(<Action>::class)->handle($this->recordOf(Profile::class,$r)->id)`. `stateIs()` is the ONE complement-of-from-state predicate (group-5 `cancel` = `stateIs('active')||stateIs('lapsed')`). `Illegal*Transition` prose-only (Pint).
- **Gated-verb test = visibility-test (all states) + reject-floor (non-from states): `assertActionHidden` + domain `toThrow(Illegal*Transition)` + state/event unchanged** — a hidden verb is undriveable via `callAction`, so assert the **domain throw**, never `assertNotified(action_failed)` (lesson 2026-06-22; except group-5 `renew` past-grace, UI-reachable → `action_failed`, design D5). `DatabaseMigrations`; happy path `callAction→assertNotified(success)`; `OriginatingClubLocked` is a one-shot **Customer**-entity event.
- **Section = one atomic commit** (groups 1–3 precedent): a numbered section's tasks + its shared `Tests` bullet ship together.
- **`*.club` i18n loanword** resolves to 'Club' in BOTH locales — the group-7 `ProfileConsoleI18nTest` distinctness set must carve out `*.club`. i18n scanner is suite-wide → verify via `--filter`, never a bare file path (task 7.1).
- **Event surface (reconciled):** approve → only `OriginatingClubLocked` (first-ever approval); decline / cancel / all Account verbs → audit-only (no event). No new `Parties\Actions\*` → `SupplyLifecycleChainTest` whitelist unchanged (design D8).
