## Why

Module K's demand-side **membership** backend is fully shipped — Profile (9-state FSM) and Account (3-state FSM), all ten Profile Actions and three Account Actions on disk — but it has **zero operator surface**. The shipped Customer console (`2026-06-22-operator-console-parties-customer`) explicitly deferred both: _"The console SHALL expose **no write affordance** for Account lifecycle or Profile lifecycle in this slice — those surfaces belong to the account and profile slices."_ This change is that slice. It gives operators the **membership approval queue** and the Profile + Account lifecycle surfaces, closing the largest UI gap in the repo (backend shipped, console nil).

## What Changes

- **New `ProfileResource`** (standalone, read-projection over `Parties\Models\Profile`): a cross-Customer list that doubles as the **membership approval queue** — the operationally central view, default-surfacing pending `Applied` — plus `ListProfiles` / `CreateProfile` / `ViewProfile` pages, mirroring the sibling Customer/Club/Producer Resources.
- **Profile create surface**: an operator create (`CreateProfile(customerId, clubId)` → born `Applied`, recording one `ProfileCreated`) — the _"operator enrols a Customer into a Club"_ path the Customer slice deferred. A duplicate non-terminal Profile per Customer–Club pair is rejected (`DuplicateProfileForClub`) and surfaced on the form.
- **Profile lifecycle verbs on `ViewProfile`** (every write routes through the existing domain Action, never `$model->save()`): **approve / decline** (the _one retained producer write_, exercised here as `newco_ops`), **activate, suspend, reactivate, lapse, renew, cancel, deactivate** — each form-less, visibility-gated to its from-state so a domain rejection is unreachable through the surface.
- **Account lifecycle verbs on `ViewCustomer`** (**suspend / reactivate / close**, all audit-only): the 1:1 co-provisioned Account, whose status the Customer slice already renders read-only.
- **EN + IT i18n** for the new `operator_console.profile.*` block and the new Account keys under `operator_console.customer.*`, with an i18n-completeness guard.
- **No new domain code, migration, composer dependency, or `party-registry` spec change** — this is a pure operator-console slice driving shipped backend.

## Capabilities

### New Capabilities

- _None._ This slice introduces no new capability; it extends the existing `operator-console`.

### Modified Capabilities

- `operator-console`: **ADD** four requirements — (1) operator creates a Profile through the console, (2) operator approves or declines a Profile (membership approval), (3) operator advances a Profile through its lifecycle, (4) operator advances a Customer's Account through its status lifecycle — **and MODIFY** the two shipped Customer requirements (_"Operator advances a Customer through its status lifecycle"_ and _"The Customer console surfaces the orthogonal compliance and membership context read-only"_) to surface the Account verbs, retire the Account/Profile-lifecycle deferral, and cross-reference the new `ProfileResource`. The cross-cutting requirements already in the capability (read-projection / write-through, `actor_role` audit envelope, EN/IT localization) are **not** modified — the new requirements inherit them and restate the discipline inline, exactly as the Customer and KYC/sanctions slices did.

## Impact

- **New files**: `app/Modules/OperatorPanel/Filament/Resources/Parties/ProfileResource.php` + `ProfileResource/Pages/{ListProfiles,CreateProfile,ViewProfile}.php`; tests under `tests/Feature/Modules/OperatorPanel/Parties/`.
- **Modified files**: `CustomerResource/Pages/ViewCustomer.php` (+3 Account verbs); `lang/en/operator_console.php` and `lang/it/operator_console.php` (+`profile.*`, +`customer.actions`/`customer.notifications` Account keys).
- **Domain driven, NOT modified** (all verified on disk): `Parties\Actions\{CreateProfile, ApproveProfile, DeclineProfile, ActivateProfile, SuspendProfile, ReactivateProfile, LapseProfile, RenewProfile, CancelProfile, DeactivateProfile, SuspendAccount, ReactivateAccount, CloseAccount}`.
- **Slice boundary — deliberately NOT in this change**:
  - `Applied → WaitingList` — the enum case exists but has **no writer**; it awaits the future `parties-hero-package` change. The console renders the state read-only if present but authors no transition into it.
  - The **Hero-Package capacity cap** at activation — a deferred **Module-A seam**; `ActivateProfile` ships **uncapped** (party-registry spec). The console drives activation without a capacity check and invents no cap.
  - The **Producer-Portal** producer-facing approve/decline UI — a separate, deferred TanStack frontend. Here the operator exercises the one producer write via `newco_ops` (DEC-083 / DEC-115 admin-parity).
  - The `MembershipFeePaid`-driven activation/renewal trigger — a deferred **Module-E seam**; the console drives activation and renewal directly.
- **No open ADR gate** is stepped through (no queue consumer, object storage, infra, or frontend-stack decision). Rides existing ADRs: read-binding/write-through (`2026-06-19`), hold-status-coupling (`2026-06-19`), non-catalog lifecycle-trait reuse (`2026-06-20`), operand-enum carve-out (`2026-06-21`).
