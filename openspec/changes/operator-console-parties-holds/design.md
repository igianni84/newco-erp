## Context

The four operator consoles shipped so far (ProductMaster, the catalog spine, Producer, Club, Customer) follow a settled kit: a read-only `OperatorConsoleResource` for list/infolist, write-through via domain Actions (never `$model->save()` — `NoEloquentWriteInOperatorPanelRule` guards it), and — for non-catalog status FSMs — a `ViewRecord` page that `use`s the `SurfacesDomainActions` trait and assembles its own header verbs (ADRs `2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse`, `2026-06-21-operator-console-rule-of-three-trait-vs-verb-list` [CLOSED]).

The Customer console (`archive/2026-06-22-operator-console-parties-customer`) shipped the four **direct** status verbs (activate / suspend / reactivate / close — the BR-K-Customer-1 "manual" path) and renders `status` / `kyc_status` / `sanctions_status` / Account status / Profiles read-only. It **explicitly deferred** the Hold place/lift surface to a compliance slice and left a forward note (its design D4/D5): the Hold-mediated status path must be **additive** to the direct verbs, and the compliance console is the missing driver that sets `sanctions_status = passed` / clears KYC for the activation gate.

The Module K Hold backend is shipped and unchanged by this slice. Verified signatures (zero-invention):

- `PlaceHold::handle(HoldType $type, HoldScope $scope, int $scopeId, ?string $reason = null): Hold` — records `CustomerHoldPlaced`; in the same transaction drives every covered scope **currently in its suspendable from-state** to `suspended` via `SuspendCustomer` / `SuspendAccount` / `SuspendProfile`.
- `LiftHold::handle(int $holdId, ?string $reason = null): Hold` — re-reads under lock; rejects a non-`active` Hold (`IllegalHoldLift::notActive`) and an auto-managed type (`IllegalHoldLift::autoManaged` — `kyc` / `payment`); records `CustomerHoldLifted`; restores each covered scope **iff no other active Hold still covers it**.
- `HoldType` { Admin, Kyc, Payment, Fraud, Compliance, Credit } with `autoLiftable(): bool` (true for `Kyc` / `Payment`). `HoldScope` { Customer, Account, Profile }. `HoldStatus` { Active, Lifted }.
- `Hold` (`parties_holds`) — `$guarded = []`, **no Eloquent relationships, no query scopes**; `scope_id` is a polymorphic id with no FK; casts `hold_type` / `scope_type` / `status` / actor roles.

## Goals / Non-Goals

**Goals:**

- Surface `PlaceHold` and `LiftHold` for a Customer through the existing `ViewCustomer` page, within the read-projection / write-through discipline.
- Render the Customer's Holds (its own + Account + Profile scope) read-only, with per-row Lift on the operator-liftable, active subset.
- Let the Hold-driven status coupling run as the domain's own behaviour, **additive** to the shipped direct status verbs; never re-derive or suppress it.
- Exercise the operand-enum carve-out cleanly (import `HoldType` / `HoldScope` as `PlaceHold` operands; render state via cast).
- EN + IT copy + completeness test; a PG17 closing-chain test for the coupling.

**Non-Goals:**

- KYC writes, sanctions write (→ `operator-console-parties-kyc-sanctions`). Direct Account/Profile verbs, anonymisation/GDPR, Originating-Club, enhanced-KYC, expired-Hold auto-transition, the `payment` / `credit` auto-triggers (later slices / deferred per spec).
- Any change to the Hold domain backend, a new read contract, a migration, or a composer dependency.
- Re-deciding the rule-of-three (CLOSED — trait level), or extending the shared `SurfacesDomainActions` trait's contract.

## Decisions

**D1 — Trait level, on the existing `ViewCustomer` page; no new base class.** The Holds surface lives on `ViewCustomer` (`extends ViewRecord`, `use SurfacesDomainActions`) and `CustomerResource` (the infolist). No `OperatorConsoleViewRecord` (catalog-only). _Per ADR 2026-06-21-rule-of-three (CLOSED): stay at the trait level._

**D2 — Operand-enum carve-out is exercised; `ModuleBoundariesTest` needs NO change.** `ViewCustomer` imports `Parties\Enums\HoldType` and `HoldScope` and constructs them with `::from($formValue)` (the `CreateClub` → `ClubRegistrationFlowType` precedent) to call `PlaceHold`. The boundary allowlist is namespace-prefix-based and **already admits the whole `Parties\Enums` prefix for OperatorPanel** (`moduleBoundaryAllowedImports()`, pinned by the carve-out guard test). `HoldStatus` is a **state** enum — render via the model cast (`->value`), never import. _ADR 2026-06-21-operand-enum-carveout._

**D3 — `PlaceHold` is a bespoke header Action reusing `surfaceLifecycleOutcome()`, not `lifecycleAction()`.** The trait's `lifecycleAction($verb, $successKey, $invoke, $form, $confirmationKey)` passes the invoke closure only the page record + a single `$notes` string — it is shaped for the bare-`int $id` status verbs. `PlaceHold` needs three operands (`HoldType`, `HoldScope`, the scope target) plus `reason`. So the `Place Hold` action is built directly (`Action::make('placeHold')->form([...])->action(fn (array $data) => $this->surfaceLifecycleOutcome(fn () => app(PlaceHold::class)->handle(...), $successTitle))`), **reusing** the trait's `surfaceLifecycleOutcome()` for the uniform `RuntimeException`→`action_failed` danger notification. The trait is **not** modified (avoids changing the shared kit contract for one caller). _Alternative — extend `lifecycleAction` to pass full `$data`: rejected; it widens the shared seam for every console to serve one multi-operand verb._

**D4 — `PlaceHold` form + scope-target resolution.** Fields: `hold_type` Select over `HoldType::cases()`; `scope_type` Select over `HoldScope::cases()`; a dependent `profile_id` Select over `$record->profiles` (visible only when `scope_type = profile`); an optional `reason` Textarea. `scopeId` is resolved from the page's Customer: `customer` → `$record->id`; `account` → `$record->account->id` (the co-provisioned Account, always present); `profile` → the selected `profile_id`. A console-created Customer has zero Profiles, so the profile Select may be empty — profile-scope placement is simply unavailable for a profile-less Customer (not an error). All six `HoldType`s are offered: the spec mandates a manual operator-placement path for **every** type (§4.8.1; trigger-agnostic registry).

**D5 — Per-row `Lift` in a Holds table; the console's first per-row action.** `LiftHold` keys off a specific **Hold id**, which no shipped verb does (`recordOf($record)->id` gives the Customer id, not a Hold id). So the Holds are rendered as a table on the Customer view sourced by a direct `Hold::query()` over the Customer's scope-set (its own id + its Account id + its Profile ids — there is no Eloquent relation to lean on), with a per-row `Lift` `Action` whose closure calls `$this->surfaceLifecycleOutcome(fn () => app(LiftHold::class)->handle($row->getKey(), $reason), $successTitle)`. **Row-action visibility** = `status === Active && ! $row->hold_type->autoLiftable()` (i.e. `admin` / `fraud` / `compliance` / `credit`). _Alternative — a header `Lift` with a Select of active liftable Holds: rejected (worse UX; the table must be rendered regardless)._ The exact Filament 5 vehicle for a **non-relation** table with row actions on a `ViewRecord` page (table widget vs. an `InteractsWithTable` component) is an implementation choice for task 1, pinned against the installed Filament version — never written from memory (the repo's arch-test discipline).

**D6 — Lift discipline is surfaced by visibility AND enforced by the domain.** The row-action visibility hides Lift on `kyc` / `payment`; the domain still rejects an operator lift of those (`IllegalHoldLift::autoManaged`) and of an already-lifted Hold (`IllegalHoldLift::notActive`), surfaced via `surfaceLifecycleOutcome`'s base-`RuntimeException` catch as `action_failed`. **Do not import** `IllegalHoldLift` (caught by base type — keeps the `{Models, Actions, Enums}` surface). _ADR 2026-06-18-hold-lift-discipline-per-type._

**D7 — The Hold-driven status coupling is domain-owned and additive.** `PlaceHold` / `LiftHold` move Customer/Account/Profile status themselves (via the `Suspend*` / `Reactivate*` Actions, the sole status writers) and record the status events. The console invokes **only** place/lift; it never calls a status Action from the Holds surface and never recomputes suspension from Holds. This coexists with the shipped direct verbs (the manual path). The view renders `status` from its authoritative cast — the coupling's effect shows through the existing status badge. _ADR 2026-06-19-hold-status-coupling; predecessor design D4._

**D8 — Spec delta: one ADDED + two minimal MODIFIED, plus a stale-guard fix.** ADD _Operator places and lifts Customer Holds through the console_. MODIFY the two Customer requirements to drop **Holds** from their negative-scope assertions (cross-referencing the new requirement); KYC / sanctions / Account / Profile negatives remain. Update `CustomerLifecycleConsoleTest`'s `assertActionDoesNotExist('placeHold' / 'liftHold')` guards (keep `requireKyc` / sanctions / account / profile — still deferred).

**D9 — PG17 closing-chain test mirrors the predecessor idiom.** `uses(DatabaseMigrations::class)` (not `RefreshDatabase` — each console action opens its own `DB::transaction` and the in-transaction event append must really commit); `Livewire::test(ViewCustomer::class, ['record' => $id])->callAction(...)`/`callTableAction(...)`; assert via `DomainEvent::query()->where('name', …)` with the strict `module`/`entity_type`/`entity_id` envelope and **loose** `actor_id` `toEqual` (PG bigint-as-string). The chain: place `admin` Hold on an `active` Customer → `suspended` + `CustomerHoldPlaced` + `CustomerSuspended`; place a second (`fraud`) Hold → still `suspended`; lift `admin` (coverage remains) → **no** restore; lift `fraud` (last covering) → `CustomerReactivated`; place an `admin` Hold on a `pending` Customer → Hold recorded, **no** transition; assert the per-row Lift is **absent** on a `kyc` Hold row and an operator lift of it is rejected.

## Risks / Trade-offs

- **First per-row action in the console (new pattern).** → The shipped consoles have only header actions. Mitigation: pin the Filament 5 non-relation-table + row-action API in task 1 against the installed version; reuse `surfaceLifecycleOutcome()` so the reject/notify behaviour is identical to every other console; keep the action's write path through `app(LiftHold::class)->handle(...)` so `NoEloquentWriteInOperatorPanelRule` stays green.
- **Direct `Hold::query()` in the console (no read contract).** → `PartyComplianceStatusReader::forCustomer()` returns only the distinct active `HoldType` list, not per-Hold rows or ids — and `LiftHold` needs the id. Mitigation: read the `Hold` model directly (within the `{Models}` carve-out; the Customer console already reads the `Profile` model for its infolist). A future read contract could consolidate the scope-set query, but adding domain code is out of this slice's scope.
- **Multi-operand `PlaceHold` form bypasses `lifecycleAction`.** → Slightly more bespoke page code than the status verbs. Mitigation: still reuse `surfaceLifecycleOutcome()` + the operand-construction (`::from`) pattern; do not fork the trait.
- **Scope-set query correctness.** → "This Customer's Holds" spans `customer` (its id), `account` (its Account id) and `profile` (its Profile ids) with no FK. Mitigation: build the OR-of-scopes from within-module reads (`$record->account`, `$record->profiles`); the PG17 chain test seeds Holds at multiple scopes to prove coverage and restoration.

## Open Questions

- **Lifted Holds in the table?** Decision (revisitable): show **both** active and lifted Holds, the `status` column distinguishing them — active rows are actionable (Lift on liftable types), lifted rows are historical/read-only. Keeps the audit trail visible on the surface.
- None blocking. No open-ADR gate is crossed (operator auth shipped; no document storage; no queue/object-store/frontend gate touched).
