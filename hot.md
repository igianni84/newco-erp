---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`operator-console-parties-membership` — group 1 GREEN, 3/16 tasks).** Shipped the demand-side **membership** console read surface as one atomic unit (the `getPages()` eager-reference coupling — precedent `991de04`): **`ProfileResource`** (read-only over `Parties\Models\Profile`) whose list = the cross-Customer **approval queue** (`ListProfiles` `getTabs()`: default "Pending" = `where('state','applied')` + "All"); **`ViewProfile`** (`ViewRecord` + `SurfacesDomainActions`, `i18nKey 'profile'`, empty header actions — verbs land groups 3–5); **`CreateProfile`** scaffold (throws "wired in 2.1"); EN/IT `operator_console.profile.*` i18n (DEC-127). No `Parties\Enums` import (state via cast, design D2); no `lifecycleStateColumn()`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session, group 1):** full suite **1565/1565** (8522 assertions, SQLite) · PHPStan max 0 · Pint clean · `openspec validate operator-console-parties-membership --strict` valid. Arch `NoEloquentWriteInOperatorPanelRule` + `ModuleBoundariesTest` green over the new classes.
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (OOMs at 128 MB; lesson 2026-06-20). PG17 close-ritual run deferred to task 8.1.

## Active Change & Next Task
- **`operator-console-parties-membership` — group 1 done; 13 tasks left.** Next: **2.1** Profile create surface. First read `app/Modules/Parties/Actions/CreateProfile.php` (confirm `handle($customerId, $clubId)` signature + gate), then replace `CreateProfile::createViaAction`'s group-1 `LogicException` with `app(CreateProfile::class)->handle((int) $data['customer_id'], (int) $data['club_id'])`, add the two Selects (Customer email+name, Club `display_name`) to `ProfileResource::form()`, surface `DuplicateProfileForClub` on `club_id`. Also add the list-header create LINK + `actions.create` i18n (deferred out of group 1). Then 2.2 (`fields.{customer,club}` + create labels EN/IT).

## Blockers & Decisions Needed
- **No blocker.** Group 2 wires `CreateProfile` (born `Applied`, one `ProfileCreated`, `actor_role newco_ops`). Boundary seams unchanged (out of scope, documented in design Non-Goals): `Applied→WaitingList` (no writer), activation capacity cap (Module-A seam — uncapped), `MembershipFeePaid` (Module-E seam), Producer-Portal TanStack UI.

## Open Patterns
- **Standing console landmines (groups 3–6):** a from-state-hidden verb is undriveable → assert the **domain throw** + `assertActionHidden`, NOT a notification (lesson 2026-06-22) — **except** `renew` past-grace (UI-reachable → `action_failed`, design D5). `{@see \…\Illegal*Transition}` in a docblock makes Pint add a boundary-breaching `use` → **prose only**. i18n scanner is suite-wide → verify via `--filter`, never a bare file path (task 7.1).
- **Event surface (reconciled):** approve → **only** `OriginatingClubLocked` on first-ever approval; decline / cancel / all Account verbs → **audit-only** (no event). No new `Parties\Actions\*` → `SupplyLifecycleChainTest` whitelist unchanged (design D8).
- **Tabs tested via `->set('activeTab', …)`** (public `HasTabs::$activeTab`).
