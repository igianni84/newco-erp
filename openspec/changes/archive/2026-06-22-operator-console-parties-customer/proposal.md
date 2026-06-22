## Why

The supply-side trilogy (Producer / Club / ProducerAgreement) shipped an operator console over Module K's supply side, but the **demand side has no operator surface**. The Customer — Module K's natural-person registry, carrying three orthogonal lifecycles (status FSM, KYC, sanctions), a co-provisioned Account and multi-Profile membership — has fully-shipped domain Actions and zero console. This change adds the read-only Customer console + create + status-FSM verbs. It is also the **first demand-side console**, the "least kit-shaped surface" the supply-side change deliberately deferred to as the stress-test that resolves the rule-of-three / D10 question (generalize the View base, or keep the trait).

## What Changes

- **New read-only `CustomerResource`** under `OperatorPanel`, read-binding `Parties\Models\Customer`, extending the shipped `OperatorConsoleResource`. List + infolist surface the Customer's **status, KYC status, sanctions status, co-provisioned Account status, and Profiles — all read-only** (rendered via model casts / within-module reads; the three lifecycles are orthogonal badges, not states of the status FSM).
- **Create surface** (`CreateCustomer` page extending `OperatorConsoleCreateRecord`): write-through via the `CreateCustomer` domain Action — collects email, name, preferred currency, preferred locale, optional phone, optional date of birth; co-provisions the Account; never `$model->save()`. Born `pending`.
- **`ViewCustomer` status-FSM verb-set**: `activate` / `suspend` / `reactivate` / `close`, each a form-less header action wired to its domain Action (`ActivateCustomer` / `SuspendCustomer` / `ReactivateCustomer` / `CloseCustomer`) via the `SurfacesDomainActions` trait.
- **EN + IT i18n** for the `customer` console block (IT omits `label`/`plural_label`, per-key EN fallback — DEC-127 convention).
- **Tests**: a kit-key i18n completeness test + a **PG17 closing-chain** integration test driving the full FSM through the Filament pages.
- **ADR (2026-06-21)** closing the rule-of-three / D10 deferral: non-catalog consoles **continue at the trait level**; no verb-list base is extracted from `OperatorConsoleViewRecord` (which stays catalog-shaped).

**Slice boundary — deliberately NOT in this change** (each named to its future slice):
- **Hold registry** (`PlaceHold` / `LiftHold`) and the **Hold-driven suspend/restore coupling** surface → `operator-console-parties-compliance`. The direct status verbs here are the **BR-K-Customer-1 "manual" path**; the Hold-mediated path is additive and coexists (ADR 2026-06-19 hold-status-coupling).
- **KYC writes** (`RequireKyc` / `RecordKycVerified` / `RecordKycRejected`) and **sanctions writes** (`RecordCustomerScreening`), enhanced-KYC → `operator-console-parties-compliance`.
- **Profile lifecycle** (approve/activate/suspend/cancel/lapse/renew) → a `…-profile` slice.
- **Account lifecycle** (`SuspendAccount` / `ReactivateAccount` / `CloseAccount`) → a `…-account` / compliance slice.
- **Anonymisation / GDPR erasure, Originating-Club mutation, Supplier create** → later slices / consumer & producer portals.

No new domain code, no migration, no composer dependency, no module-boundary change (create operands are platform-level — `Currency`, `SupportedLocale` — so the operand-enum carve-out is not exercised). No open-ADR gate is crossed (operator auth shipped; no document storage).

## Capabilities

### New Capabilities

_None._ This change extends the existing operator-console capability.

### Modified Capabilities

- `operator-console`: **ADD** three Customer requirements — *Operator creates a Customer through the console*, *Operator advances a Customer through its status lifecycle*, *The Customer console surfaces the orthogonal compliance and membership context read-only*. Modeled on the shipped Producer trio. The cross-cutting requirements already in the capability (read-projection/write-through, `actor_role` audit envelope, EN/IT localization) are **not** modified — the new requirements inherit them and restate the discipline inline, exactly as the supply-side per-entity requirements did.

## Impact

- **New code**: `app/Modules/OperatorPanel/Filament/Resources/Parties/CustomerResource.php` + `CustomerResource/Pages/{ListCustomers,CreateCustomer,ViewCustomer}.php`.
- **i18n**: `lang/en/operator_console.php` + `lang/it/operator_console.php` (add `customer` block).
- **Tests**: `tests/Feature/Modules/OperatorPanel/Parties/Customer*ConsoleTest.php`, `CustomerConsoleI18nTest.php`, `CustomerConsoleChainTest.php`.
- **Decisions**: new ADR `decisions/2026-06-21-operator-console-rule-of-three-trait-vs-verb-list.md` + `decisions/INDEX.md` update.
- **Reads (no change)**: shipped Parties Actions/Models/Events/Enums; the shipped operator-console kit; `database/factories/Parties/CustomerFactory.php` (chain-test seed).
- **No** migration, **no** domain Action change, **no** `openspec/specs/party-registry` change (the domain truth it owns is consumed, not modified).
