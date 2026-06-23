---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`operator-console-parties-membership` — group 8.1 GREEN, 15/16 tasks).** Shipped the change's **PG17 closing-chain integration proof** `tests/Feature/Modules/OperatorPanel/Parties/ProfileMembershipChainTest.php` — ONE self-contained `it` (`DatabaseMigrations`) driving the WHOLE membership console end-to-end through the Filament PAGES, then composing the orthogonal Account FSM on the SAME Customer. **Profile FSM:** CreateProfile page (→`Applied`) → ViewProfile approve (→`Approved`; asserts `OriginatingClubLocked` is a **Customer**-entity event + the Customer's `originating_club_id` locked) → activate (→`Active`, uncapped) → suspend → reactivate → lapse (stamps `lapsed_at=now`) → renew (within-grace) → **deactivate** (→`Inactive`, the evented terminal; `cancel` = audit-only sibling, covered per-task). Re-mounts the page per verb. **Ordered 8-event sequence** (`orderBy('id')->pluck('name')`): ProfileCreated, OriginatingClubLocked, ProfileActivated, ProfileSuspended, ProfileReactivated, ProfileExpired, ProfileRenewed, ProfileInactive — pinned after the Profile walk AND after the Account chain. **Account FSM** (suspend→reactivate→close on ViewCustomer): the sequence is byte-identical after → +0 events, no cascade (orthogonality, AC-K-FSM-9). Final: Profile `Inactive`, Customer still `Pending`, Account `Closed`. Verified gates on disk first (root §4): CreateProfile gates only on duplicate-pair; ActivateProfile only on `Approved`; RenewProfile needs `lapsed_at` within 30d.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session, 8.1):** filtered **1/1** (98 assertions, SQLite) · full suite **1726/1726** (9425 assertions, SQLite) · PHPStan max 0 · Pint clean · `openspec validate operator-console-parties-membership --strict` valid. **PG17 ritual GREEN:** throwaway `postgres:17` Docker, `DB_CONNECTION=pgsql … pest --filter=ProfileMembershipChainTest` → 1/1 (98 assertions) on PostgreSQL 17, container torn down.
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (OOMs at 128 MB; lesson 2026-06-20).

## Active Change & Next Task
- **`operator-console-parties-membership` — groups 1–8.1 done; 1 task left.** Next: **8.2** (FINAL gate) = re-confirm full-suite `php -d memory_limit=-1 vendor/bin/pest` green + `php -d memory_limit=-1 vendor/bin/phpstan analyse` (max, 0) + `vendor/bin/pint --test` clean + `openspec validate operator-console-parties-membership --strict` valid (all green as of 8.1). Confirm **no new `Parties\Actions\*`** (this slice drives existing Actions) → `SupplyLifecycleChainTest` whitelist unchanged (design D8): `grep` `app/Modules/Parties/Actions/` for any new file. No new ADR / no `decisions/INDEX.md` change expected. Consolidate `progress.md` `## Codebase Patterns`. Then reply exactly `<promise>CHANGE_COMPLETE</promise>` (do NOT archive/merge — humans do that).

## Blockers & Decisions Needed
- **No blocker.** Boundary seams unchanged (out of scope, design Non-Goals): `Applied→WaitingList` (no writer), activation capacity cap (Module-A seam — activation ships uncapped), `MembershipFeePaid`/renewal trigger (Module-E seam), Producer-Portal TanStack UI.

## Open Patterns
- **Closing chain = `CustomerConsoleChainTest` shape, COMPOSED across two FSMs** (group 8): ONE `it`, `DatabaseMigrations`, drive the WHOLE happy-path FSM through the PAGES (re-mount per verb), assert the ORDERED event-name sequence (`orderBy('id')->pluck('name')` → plain-string array, each == its `*::NAME`); a second orthogonal audit-only FSM is proven by the SAME sequence re-asserted unchanged (+0 = no event + no cascade). Pick the EVENTED terminal for non-vacuity. PG17 ritual safe via `--filter` for a self-contained test (no shared top-level helper). Detail in `progress.md` Codebase Patterns.
- **Lifecycle verb (own/related record):** `lifecycleAction($verb,$key,$invoke)->visible(fn () => $this->stateIs/accountStatusIs('<from>'))`; cast `->value`, no enum import; related entity routes by nested `?->id`. No new `Parties\Actions\*` → `SupplyLifecycleChainTest` whitelist unchanged (design D8).
