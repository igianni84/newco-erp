## Context

First **demand-side** operator console over Module K's shipped Customer backend. The supply-side trilogy (`2026-06-21-operator-console-parties-supply-side`) established the recipe and the kit (`app/Modules/OperatorPanel/Filament/Console/`): a read-only `OperatorConsoleResource`, a write-through `OperatorConsoleCreateRecord`, and the `SurfacesDomainActions` trait. This change reuses that kit verbatim for the Customer entity. Read the predecessor `## Codebase Patterns` (`openspec/changes/archive/2026-06-21-operator-console-parties-supply-side/progress.md`) before starting.

The Customer is the **least kit-shaped surface** the supply-side change pointed at: three orthogonal lifecycles on one record (status FSM, KYC, sanctions), a co-provisioned Account, and multi-Profile membership. That is exactly why it is the agreed stress-test for the rule-of-three / D10 question (see D8). No new domain code, migration, composer dependency, or module-boundary change. No open-ADR gate crossed.

Two verified facts drove the scope (both differ from first assumptions and are load-bearing):
1. `CreateCustomer` co-provisions the **Account only** (event-silent), **not** a Profile — a new Customer has its Account and zero Profiles.
2. `ActivateCustomer`'s gate is **composite and cross-slice** — it reads onboarding-acceptance timestamps (consumer flow) and `sanctions_status = passed` (compliance console), neither of which this slice can set. See D5.

## Goals / Non-Goals

**Goals:**
- Read-only `CustomerResource` (list + infolist) surfacing status / KYC / sanctions / Account status / Profiles read-only.
- Create surface routing through `CreateCustomer` (write-through, co-provisions Account), born `pending`.
- `ViewCustomer` with the full status-FSM verb-set: activate / suspend / reactivate / close.
- EN + IT i18n; i18n kit-key completeness test; PG17 closing-chain integration test.
- Close the rule-of-three / D10 deferral with an ADR.

**Non-Goals (each named to its future slice):**
- Hold registry (`PlaceHold` / `LiftHold`) and the Hold-driven suspend/restore coupling surface → `operator-console-parties-compliance`.
- KYC writes (`RequireKyc` / `RecordKycVerified` / `RecordKycRejected`), sanctions writes (`RecordCustomerScreening`), enhanced-KYC → compliance slice.
- Profile lifecycle (approve / activate / suspend / cancel / lapse / renew) → `…-profile` slice.
- Account lifecycle (`SuspendAccount` / `ReactivateAccount` / `CloseAccount`) → account / compliance slice.
- Anonymisation / GDPR erasure, Originating-Club mutation, Supplier create, consumer/producer portals.

## Decisions

- **D1 — Reuse the trait, not the catalog View base.** `ViewCustomer` extends `\Filament\Resources\Pages\ViewRecord` and `use SurfacesDomainActions`, assembling its own `getHeaderActions()`. Do **not** extend `OperatorConsoleViewRecord` (catalog-shaped, five governance verbs). `CustomerResource` extends `OperatorConsoleResource`; `CreateCustomer` page extends `OperatorConsoleCreateRecord`. _(Alternative: extend the catalog View base — rejected, the Customer FSM is not the catalog five.)_
- **D2 — State enums render via cast, never imported.** `status` / `kyc_status` / `sanctions_status` / Account `status` are displayed with a `getStateUsing` + `instanceof BackedEnum` → `->value` badge, no `Parties\Enums` import. Do **not** call `lifecycleStateColumn()` (that is the catalog `lifecycle_state`, not Customer's `status`).
- **D3 — Form-less verbs, no SoD, no review verbs.** All four status verbs have no form and no `confirmationKey` (the spec mandates no separation-of-duties for Customer, Admin_Panel §5.2). No submit/reject/reopen (the Customer FSM is not review-governed).
- **D4 — The full status FSM is surfaced DIRECTLY (the "manual" path).** activate/suspend/reactivate/close wire straight to their domain Actions. Per BR-K-Customer-1, suspension is "explicit (manual or via Hold)"; these direct verbs are the manual path. The **Hold-mediated path** (`PlaceHold`/`LiftHold`, whose coupling also moves status — ADR 2026-06-19) is the compliance slice's surface and **coexists additively** — it is not duplicated here and must not be re-derived there. _(Alternative: defer suspend/reactivate to compliance and surface only create+activate+close — rejected with the user: leaves the core FSM un-demonstrable and weakens the rule-of-three stress-test.)_
- **D5 — Activation gate is cross-slice; the console surfaces the verb, the Action's guard enforces.** `ActivateCustomer` requires email-verified + T&C/privacy accepted + `sanctions_status = passed` + KYC-cleared-if-required. This slice sets **none** of those (they come from the consumer-onboarding flow and the compliance console). The console surfaces `activate`; a gate-unmet attempt rejects (`IllegalCustomerTransition` gate-not-met) and renders a danger notification for free via the trait's `surfaceLifecycleOutcome`. **This is correct domain behaviour, not a bug** — the console is a surface ahead of its drivers. LANDMINE: do not try to "fix" the rejection by setting gate columns from the console.
- **D6 — Create operands are platform-level → no boundary change.** `CreateCustomer::handle()` takes `App\Platform\Money\Currency` and `App\Platform\I18n\SupportedLocale` (both always-importable) plus scalars. The create page constructs `Currency::of($code)` and `SupportedLocale::from($value)` — **no** `Parties\Enums` operand-enum import, so the operand-enum carve-out (ADR 2026-06-21) is **not exercised** and `ModuleBoundariesTest` needs no widening (unlike the Club slice).
- **D7 — `CreateCustomer` co-provisions the Account only, event-silent.** It records exactly one `CustomerCreated`; the Account is born `Personal`/`active` with **no** `AccountCreated` event (none exists — do **not** invent one). It creates **no** Profile. The infolist's Profiles panel is empty for a console-created Customer.
- **D8 — Rule-of-three / D10 CLOSED: continue at the trait level; no verb-list base.** With Customer (the least kit-shaped surface) now in hand, the verdict is that `extends ViewRecord + use SurfacesDomainActions + bespoke getHeaderActions()` handles it cleanly; a verb-list base extracted from `OperatorConsoleViewRecord` would have to absorb per-entity divergence the trait deliberately leaves free (verb count, the read-only multi-FSM infolist, differently-named state columns, the cross-slice gate). `OperatorConsoleViewRecord` stays catalog-only. Recorded in `decisions/2026-06-21-operator-console-rule-of-three-trait-vs-verb-list.md` (authored with this change — implement to it, do not re-decide). _(Alternative: extract a verb-list base now — rejected; it imposes structure ahead of evidence the divergences fit, against the recipe-extraction finding.)_
- **D9 — The closing-chain test seeds gate-met + drives the page create.** `CustomerFactory` is event-free (bypasses `CreateCustomer`, no Account, no event) — so the chain (a) creates one Customer through the `CreateCustomer` **page** (born `pending`, records `CustomerCreated`) and attempts a gate-unmet `activate` (rejected, **no** event — documents D5); then (b) seeds a **gate-met, profile-less** Customer via `Customer::factory()->create([... email_verified_at/tc_accepted_at/privacy_accepted_at set, sanctions_status: Passed, kyc_required left null so the KYC rider clears ...])` and drives activate → suspend → reactivate → close through the pages. Seeding **profile-less** keeps the emergent event set to the five Customer events (no `ProfileSuspended`/`ProfileReactivated` cascade noise — that cascade is the Action's behaviour, already covered by `parties-core`).

## Risks / Trade-offs

- **`activate` idles in production until siblings ship (D5).** → Accepted: the verb+guard pattern rejects gracefully; the consumer-onboarding flow + compliance console will supply the preconditions. Documented as a surface-ahead-of-drivers, not a defect.
- **Direct suspend/reactivate create no Hold provenance (D4).** → Accepted per BR-K-Customer-1 (manual path is sanctioned). The compliance slice adds the Hold-mediated path additively; it must reference this slice to avoid duplicating the status transition.
- **Eager page-reference coupling.** `CustomerResource::getPages()` references all three pages → scaffold `List`/`Create`/`View` stubs early so the Resource boots (the supply-side landmine). Sequence: Resource+List (Group 1) scaffolds Create/View stubs; Create (Group 2) and View (Group 3) fill them.
- **i18n helper-load false-red.** Run `CustomerConsoleI18nTest` via `--filter` or the folder-wide run (append a Catalog i18n test so the shared `scanOperatorConsoleHardcodedSinks` helper loads), **not** a bare file path.
- **PG bigint-as-string.** Assert `actor_id` / `causation_id` with loose `toEqual` (PostgreSQL returns uncast bigints as numeric strings) and `entity_id` as `(string)` — the supply-side idiom that keeps the chain test green on both SQLite and PG17.
- **Profile cascade leaking into the chain assert.** → Seed profile-less (D9); the cascade is out of this console's scope.

## Migration Plan

Additive only — new Filament classes, i18n keys, tests, one ADR. No schema change, no data migration, no rollback concern. Reversible by removing the new files and the `customer` i18n block.

## Open Questions

None blocking — both interview tensions (FSM scope / suspend-reactivate boundary; rule-of-three) resolved with the user. Forward note: the compliance slice (`operator-console-parties-compliance`) will surface `PlaceHold`/`LiftHold`, whose coupling also moves Customer status; its design must reference D4 here and treat the Hold-driven transition as additive to (not a replacement of) the direct verbs shipped in this change.
