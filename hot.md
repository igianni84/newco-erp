---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`operator-console-parties-membership` — group 2 GREEN, 5/16 tasks).** Shipped the demand-side **membership-application create** surface as one atomic section commit: **`ProfileResource::form()`** (a `customer_id` Select labelled email+name + a `club_id` Select labelled `display_name`, both required, options off the within-Parties Customer/Club registries via two private `array<int,string>` helpers; NO state/tier/role field — born `applied`, DEC-062/design D6); **`CreateProfile` page** real wiring (`createViaAction()` narrows ids `is_numeric`→`(int)` then `app(CreateProfileAction::class)->handle(customerId:…, clubId:…)`; the colliding domain action aliased `as CreateProfileAction`; `createRejectionField()` `club_id` where the base maps `DuplicateProfileForClub`); **`ListProfiles` header create-LINK** (`Action::make('create')->url(…getUrl('create'))`, not a CreateAction); EN/IT `profile.fields.{customer,club}` + `actions.create`. `DuplicateProfileForClub` referenced in prose only (Pint trap dodged — imports verified clean).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session, group 2):** full suite **1569/1569** (8550 assertions, SQLite) · PHPStan max 0 · Pint clean · `openspec validate operator-console-parties-membership --strict` valid. Arch `NoEloquentWriteInOperatorPanelRule` + `ModuleBoundariesTest` green over the create wiring.
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (OOMs at 128 MB; lesson 2026-06-20). PG17 close-ritual run deferred to task 8.1.

## Active Change & Next Task
- **`operator-console-parties-membership` — groups 1–2 done; 11 tasks left.** Next: **3.1** approve/decline on `ViewProfile`. Append two form-less header actions via the kit's `lifecycleAction($verb, $successKey, $invoke)`, each `->visible()` gated to `state == 'applied'`: `approve` → `app(ApproveProfile::class)->handle($this->recordOf(Profile::class, $record)->id)`; `decline` → `app(DeclineProfile::class)->handle(...)`. **First read** `app/Modules/Parties/Actions/{ApproveProfile,DeclineProfile}.php` to confirm `handle()` signatures + the one-shot `OriginatingClubLocked` (approve, first-ever only). Catch rejections by base `\RuntimeException`; reference `IllegalProfileTransition` as **prose** (Pint trap). Then 3.2 (`actions.{approve,decline}` + `notifications.{approved,declined}` EN/IT).

## Blockers & Decisions Needed
- **No blocker.** Boundary seams unchanged (out of scope, design Non-Goals): `Applied→WaitingList` (no writer), activation capacity cap (Module-A seam — uncapped, task 4.1), `MembershipFeePaid` (Module-E seam), Producer-Portal TanStack UI.

## Open Patterns
- **Section = one atomic commit (group-1 precedent).** A numbered section's tasks + its single shared `Tests` bullet ship together (group 2 = 2.1 form + 2.2 i18n + `ProfileCreateConsoleTest`, both checkboxes flipped in one commit).
- **Write-through create = CustomerCreateConsoleTest shape:** alias the colliding action, narrow ids `is_numeric`→`(int)`, `createRejectionField` names the field the domain `RuntimeException` lands on. Create-test uses **`DatabaseMigrations`** (the action's `DB::transaction` + DomainEventRecorder must really commit), drive via `Livewire::test→fillForm→call('create')`.
- **`*.club` i18n loanword:** resolves to 'Club' in BOTH locales — the group-7 `ProfileConsoleI18nTest` distinctness set must carve out `*.club` (same as the Club console's `label`/`plural_label`).
- **Standing console landmines (groups 3–6):** a from-state-hidden verb is undriveable → assert the **domain throw** + `assertActionHidden`, NOT a notification (lesson 2026-06-22) — **except** `renew` past-grace (UI-reachable → `action_failed`, design D5). `{@see \…\Illegal*Transition}` in a docblock makes Pint add a boundary-breaching `use` → **prose only**. i18n scanner is suite-wide → verify via `--filter`, never a bare file path (task 7.1).
- **Event surface (reconciled):** approve → **only** `OriginatingClubLocked` on first-ever approval; decline / cancel / all Account verbs → **audit-only** (no event). No new `Parties\Actions\*` → `SupplyLifecycleChainTest` whitelist unchanged (design D8).
- **Tabs tested via `->set('activeTab', …)`** (public `HasTabs::$activeTab`).
