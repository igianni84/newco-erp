---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`operator-console-parties-membership` — group 6 GREEN, 13/16 tasks).** Shipped the **Account status-FSM** verbs on `ViewCustomer` as one atomic section commit: `getHeaderActions()` now appends three form-less `lifecycleAction(...)->visible(fn () => …)` verbs after the KYC verbs (before bespoke `placeHold`/`recordScreening`): `suspendAccount` (`active→suspended`, key `account_suspended`) → `SuspendAccount`; `reactivateAccount` (`suspended→active`, `account_reactivated`) → `ReactivateAccount`; `closeAccount` (`active||suspended→closed` via `accountStatusIs('active')||accountStatusIs('suspended')`, `account_closed`) → `CloseAccount`. All AUDIT-ONLY (no Account event on disk), routing by the NESTED `(int) …->account?->id` (the `holdScopeId` precedent). New helper `accountStatusIs(string)` reads `…->account?->status->value === $status` (two-hop nullsafe, PHPStan-clean; NO `Parties\Enums` import — design D2). No `ActivateAccount` (born active, AC-K-FSM-9). i18n `customer.actions.{suspend,reactivate,close}_account` + `customer.notifications.account_{suspended,reactivated,closed}` EN+IT (distinct). `IllegalAccountTransition` prose-only (Pint trap dodged, verified post-Pint).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session, group 6):** full suite **1645/1645** (9166 assertions, SQLite) · PHPStan max 0 · Pint clean · `openspec validate operator-console-parties-membership --strict` valid. New `AccountLifecycleConsoleTest` 11/11 (71 assertions). Arch `NoEloquentWriteInOperatorPanelRule` + `ModuleBoundariesTest` green.
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (OOMs at 128 MB; lesson 2026-06-20). PG17 close-ritual run deferred to task 8.1.

## Active Change & Next Task
- **`operator-console-parties-membership` — groups 1–6 done; 3 tasks left.** Next: **7.1** = new `tests/Feature/Modules/OperatorPanel/Parties/ProfileConsoleI18nTest.php` (i18n kit-key completeness). **REUSE** the suite-wide `scanOperatorConsoleHardcodedSinks` helper behind a `function_exists(...)` guard (declared in Catalog `ProductMasterConsoleI18nTest`; do NOT redeclare — redeclaration fatals when both files load, lesson 2026-06-20). Assert: every `operator_console.profile.*` key AND the new `operator_console.customer.*` Account keys (`actions.{suspend,reactivate,close}_account` + `notifications.account_{suspended,reactivated,closed}`) resolve in `lang/en`; IT provides distinct values EXCEPT DEC-127 EN-fallback (`label`/`plural_label` omitted in IT) AND the `*.club` loanword (resolves 'Club' in both — carve it out exactly like the Club console carves its `label`/`plural_label`); IT ⊆ EN (no dangling IT key); the new Filament classes route every user-facing string through i18n (sink scan finds no hardcoded copy). **Verify via `php -d memory_limit=-1 vendor/bin/pest --filter=ProfileConsoleI18nTest`** (or full suite) — NEVER a bare single-file path (leaves shared helper undeclared → false red, lesson 2026-06-20). Then 8.1 (PG17 chain) + 8.2 (full-suite gate → CHANGE_COMPLETE).

## Blockers & Decisions Needed
- **No blocker.** Boundary seams unchanged (out of scope, design Non-Goals): `Applied→WaitingList` (no writer), activation capacity cap (Module-A seam — activation ships uncapped), `MembershipFeePaid`/renewal trigger (Module-E seam), Producer-Portal TanStack UI.

## Open Patterns
- **Lifecycle verb (own record) = `lifecycleAction($verb,$successKey,$invoke)->visible(fn () => $this->stateIs('<from>'))`** appended to `getHeaderActions()`; `stateIs()`/`kycPending()` read the cast `->value`, no enum import. Two-state gate = `||`. `Illegal*Transition`/events prose-only (Pint `{@see FQCN}` import trap).
- **Lifecycle verb (RELATED entity; group 6):** invoke routes by NESTED id `(int) …->account?->id`; visibility helper reads NESTED cast `…->account?->status->value` (two-hop nullsafe, PHPStan-clean). OVERLAPPING-from-state TEST corollary generalizes verbatim across resources: SET-based visibility sweep (`array $visibleVerbs`, `in_array`) + `list<Enum>` reject-floor (`continue` on legal-from). AUDIT-ONLY verb → assert `DomainEvent::count() === 0` + ORTHOGONALITY (Account FSM never moves Customer/Profiles, AC-K-FSM-9). Absent inverse verb → `assertActionDoesNotExist` (no `activateAccount`). Hidden-verb reject = domain `toThrow` + `assertActionHidden`, never `assertNotified(action_failed)` (lesson 2026-06-22). Tests `DatabaseMigrations`; Customer factory co-provisions NO Account → seed `Account::factory()`.
- **Section = one atomic commit** (groups 1–6). No new `Parties\Actions\*` → `SupplyLifecycleChainTest` whitelist unchanged (design D8).
