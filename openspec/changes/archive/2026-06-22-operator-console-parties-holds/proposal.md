## Why

Module K's **Hold** backend is shipped — the unified, trigger-agnostic account-restriction registry (`PlaceHold` / `LiftHold`, six types × three scopes, the per-type lift discipline, and the Hold-driven status coupling that suspends/restores the covered scope). The just-archived Customer console renders the demand-side compliance context **read-only** but exposes **no operator surface** to place or lift a Customer's Holds. Admin Panel §3.K names "Place / lift a Customer Hold" as the cross-cutting compliance action operators need. This change adds that surface — the **first slice** of the deferred Customer-compliance console (Holds-first; KYC + sanctions writes follow in a sibling change).

## What Changes

- **ADD a Holds table to the Customer view** — the Holds covering the Customer (its own scope), its co-provisioned Account, and its Profiles, each row rendering type / scope / status / reason / placement actor + moment / lift actor + moment, read from the `Hold` model within the `{Models}` read carve-out (no Eloquent relation exists — `scope_id` is polymorphic).
- **ADD a `Place Hold` header action** on `ViewCustomer` — a form collecting `HoldType` (six), `HoldScope` (three), the scope target (the Customer / its Account / a selected Profile) and an optional `reason`, invoking `PlaceHold`. This **exercises the operand-enum carve-out**: the console imports and constructs `Parties\Enums\HoldType` and `HoldScope` as `PlaceHold::handle()` operands.
- **ADD a per-row `Lift` action** on **active, operator-liftable** Hold rows (`admin` / `fraud` / `compliance` / `credit`), invoking `LiftHold($holdId, $reason)`. `kyc` / `payment` rows offer **no** Lift (the domain rejects an operator lift of an auto-managed type). This is the operator console's **first per-row action** — `LiftHold` keys off a specific Hold id, not the page record.
- **Surface (never re-derive) the Hold-driven status coupling** — `PlaceHold` suspends every covered scope currently in its suspendable from-state; `LiftHold` restores when the last covering Hold is gone. This is the domain Action's own behaviour, **additive** to the already-shipped direct status verbs (activate / suspend / reactivate / close — the manual path). The console calls place/lift only; it never calls `Suspend*` / `Reactivate*` itself.
- **MODIFY the two shipped Customer requirements** to drop **Holds** from their "no write affordance" / "no place-hold/lift-hold action" negatives. KYC, sanctions, Account-lifecycle and Profile-lifecycle stay deferred (their negatives remain).
- **EN + IT localization** for the new Hold keys + extension of the Customer console i18n completeness test; a **PG17 closing-chain integration test** exercising the place→suspend / lift→restore coupling (incl. multi-Hold coverage and place-on-`pending` no-op).
- **No** new domain code, migration, or composer dependency. **No** `ModuleBoundariesTest` change — the operator-console carve-out already admits the `Parties\Enums` prefix; this slice merely exercises it.

## Capabilities

### New Capabilities

_None._ This slice extends the existing `operator-console` capability; the Hold domain behaviour already lives in `party-registry` (consumed read-only, not modified).

### Modified Capabilities

- `operator-console`: **ADD** one requirement — _Operator places and lifts Customer Holds through the console_. **MODIFY** two existing requirements — _Operator advances a Customer through its status lifecycle_ and _The Customer console surfaces the orthogonal compliance and membership context read-only_ — to remove the now-false "no Hold write affordance" assertions (KYC / sanctions / Account / Profile remain deferred).

## Impact

- **Console code:** `app/Modules/OperatorPanel/Filament/Resources/Parties/CustomerResource.php` (Holds table on the infolist) and `…/CustomerResource/Pages/ViewCustomer.php` (the `Place Hold` header action + per-row `Lift`). Reuses the shipped `SurfacesDomainActions` trait's `surfaceLifecycleOutcome()` for the uniform reject→notification handling.
- **i18n:** `lang/en/operator_console.php` + `lang/it/operator_console.php` (extend the `customer` block).
- **Tests:** new console behaviour + read-surface tests and a PG17 closing-chain test under `tests/Feature/Modules/OperatorPanel/Parties/`; the stale `assertActionDoesNotExist('placeHold' / 'liftHold')` guards in `CustomerLifecycleConsoleTest` updated (the `requireKyc` / sanctions / account / profile guards stay — those remain deferred).
- **Domain (read-only, unchanged):** `Parties\Actions\{PlaceHold,LiftHold}`, `Models\Hold`, `Enums\{HoldType,HoldScope,HoldStatus}`, `Events\{CustomerHoldPlaced,CustomerHoldLifted}`, `Exceptions\IllegalHoldLift`.
- **Slice boundary — deliberately NOT in this change** (each named to its slice):
  - **KYC writes** (`RequireKyc` / `RecordKycVerified` / `RecordKycRejected`) and **sanctions write** (`RecordCustomerScreening`) → `operator-console-parties-kyc-sanctions` (next). Note the cross-slice link: `RequireKyc` auto-places a `kyc` Hold and `RecordKycVerified` auto-lifts it — that KYC slice's tests can verify the side-effect against the Holds table this change ships.
  - **Direct Account verbs** (`SuspendAccount` / `ReactivateAccount` / `CloseAccount`) and **Profile lifecycle** → account / profile slices. (Account/Profile status moved by the **Hold coupling** is in scope here as a domain side-effect; the direct verbs are not.)
  - **Anonymisation / GDPR erasure, Originating-Club mutation, enhanced-KYC, expired-Hold auto-transition, the `payment` / `credit` auto-triggers** (Module E/S signals) → later slices / deferred per spec.
