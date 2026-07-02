## ADDED Requirements

### Requirement: Customer Anonymisation (Right-to-Erasure)

NewCo SHALL provide an `AnonymiseCustomer` operator action that executes the GDPR right-to-erasure by **overwriting personal data in place** (never deleting rows), preserving the keyed transactional history for the retention window. In one `DB::transaction` against a transaction-locked re-read, and subject to the *Anonymisation Hold Precedence* gate, the action SHALL: (a) overwrite the Customer's PII fields — `name`, `email`, `phone`, `date_of_birth` — and the personal fields of every Address scoped to that Customer with **deterministic, per-Customer-unique placeholders** (reproducible, derived from the Customer id, **never random**) so that the globally-unique-email invariant is preserved; (b) set the Customer's `anonymised_at` timestamp; (c) **redact the Customer's own `audit_records`** by nulling their `before`/`after` snapshots via the reserved redaction path (the sole mutation the audit immutability triggers permit); and (d) record a **PII-free** `CustomerAnonymised` domain event carrying only the Customer id and `anonymised_at`. All FK-linked transactional history (Profile, Order, Voucher, Invoice) SHALL survive keyed by the now-anonymised Customer and remain queryable via the opaque anonymised identifier; vouchers SHALL remain valid.

Anonymisation SHALL be **orthogonal** to the Customer status FSM (`pending | active | suspended | closed`): the action SHALL NOT change `status`; a Customer in **any** status (typically `closed`) MAY be anonymised; and an anonymised Customer SHALL retain its status — the two are **independent operations** (`BR-K-Customer-2`). Anonymisation is a boolean-derivable state (`anonymised_at IS NOT NULL`), not a status value. Re-invoking the action on an already-anonymised Customer SHALL be an **idempotent no-op** that changes nothing and records **no** second `CustomerAnonymised`.

#### Scenario: Anonymisation severs PII and preserves keyed history

- **WHEN** `AnonymiseCustomer` is invoked on a Customer (not blocked by a `compliance` Hold) who owns Profiles and an Address
- **THEN** the Customer's `name`/`email`/`phone`/`date_of_birth` and the Address's personal fields are overwritten with deterministic placeholders, `anonymised_at` is set, the Profile/Address rows are **not** deleted (they survive keyed to the Customer), and the Customer remains queryable only as the opaque anonymised identifier

#### Scenario: Placeholders are deterministic and per-Customer-unique

- **WHEN** two distinct Customers are anonymised
- **THEN** each receives placeholders derived from its own id, the two anonymised emails differ (the globally-unique-email invariant holds), and re-deriving a placeholder for the same Customer yields the same value (reproducible, never random)

#### Scenario: Anonymisation is orthogonal to closure

- **GIVEN** a Customer in `closed`
- **WHEN** `AnonymiseCustomer` is invoked
- **THEN** the Customer is anonymised (PII overwritten, `anonymised_at` set), its `status` remains `closed` (the action writes no status transition and records no `CustomerClosed`/`CustomerReactivated`), and it stays admin-queryable as an opaque identifier

#### Scenario: CustomerAnonymised is PII-free

- **WHEN** a Customer is anonymised
- **THEN** exactly one `CustomerAnonymised` domain event is recorded in the same transaction, tagged module `parties` with the Customer's entity type and id, whose payload contains only the id and `anonymised_at` — no name, email, phone, date of birth or address

#### Scenario: The Customer's audit records are redacted

- **WHEN** a Customer whose `audit_records` carry personal data in their `before`/`after` snapshots is anonymised
- **THEN** those snapshots are nulled via the redaction path (the only mutation the immutability triggers permit — the rows themselves are neither deleted nor structurally altered), so no PII survives in the append-only audit trail

#### Scenario: Re-anonymising is an idempotent no-op

- **WHEN** `AnonymiseCustomer` is invoked on a Customer whose `anonymised_at` is already set
- **THEN** nothing changes and no second `CustomerAnonymised` event is recorded

_Source: spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 2 AC-K-J-9 (soft-delete + anonymisation FLOOR — overwrite Customer PII + Address personal fields with deterministic placeholders; HubSpot sync removes PII; history survives keyed by the anonymised identifier; vouchers remain valid), § 3 AC-K-FSM-16 (anonymisation records the moment; orthogonal to `closed`), § 4.2 AC-K-BR-Customer-2 · spec/02-prd/Module_K_PRD_v0.3-MVP.md § 8.2 (soft-delete + anonymisation; overwrite-in-place; deterministic placeholders; keyed history; vouchers valid; closed/anonymised orthogonal), § 12 (right to erasure), § 14.2 BR-K-Customer-2 · spec/04-decisions/decisions.md DEC-027 (GDPR posture — soft-delete + anonymise + 10-yr retention) · openspec/specs/event-substrate/spec.md (the `audit_records` before/after redaction path; the `redactor` role reserved for Module K's erasure job) · decisions/2026-06-12-event-substrate-and-audit-store.md + decisions/2026-06-15-identity-auth.md (the erasure seam this change completes; the RM-09 correction) · decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md (the `CustomerAnonymised` PII-free event added over the frozen spec's event-free anonymisation, as the HubSpot seam) · CLAUDE.md invariants 4 (financial immutability — corrections not deletes), 8 (audit envelope — append-only + GDPR redaction), 10 (module boundaries)._

### Requirement: Anonymisation Hold Precedence

The `AnonymiseCustomer` action SHALL enforce a regulatory-retention **Hold-precedence gate**: anonymisation SHALL be **blocked while the Customer is covered by an active `compliance` Hold**, and **no other Hold type** SHALL block anonymisation. Coverage SHALL be read through the existing within-module Hold read-contract (an active `compliance` Hold on the Customer scope). When blocked, the action SHALL raise a **localized exception**, leave the Customer **un-anonymised** (no PII overwrite, no `anonymised_at`, no audit redaction, no Address overwrite), and record **no** `CustomerAnonymised`. When the Customer's only active Holds are non-`compliance` types, anonymisation SHALL **proceed**: because Hold `reason`/`lift_reason` are controlled non-PII strings (never personal data), no Hold-metadata overwrite is required — the frozen spec's "Hold metadata anonymises alongside the PII" is satisfied by construction — and each Hold's structural blocking state is preserved.

This adopts canon **MVP-DEC-015** (`compliance`-only over the full Hold-type set; **no separate `sanctions` Hold type**) per `decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md`, which also resolves the frozen-spec contradiction (greenfield `DEC-027`: only `sanctions` blocks; PRD §8.2 / `AC-K-J-9a`: `compliance` + sanctions-OFAC block). A sanctioned Customer whose identifiable data must be retained is gated by Compliance **placing a `compliance` Hold** — sanctions state lives in the separate `sanctions_status` FSM, not a Hold type.

#### Scenario: A compliance Hold blocks anonymisation

- **GIVEN** a Customer covered by an active `compliance` Hold
- **WHEN** `AnonymiseCustomer` is invoked
- **THEN** the action raises a localized exception, the Customer is left un-anonymised (`anonymised_at` still NULL, PII intact, Address intact, audit un-redacted), and no `CustomerAnonymised` event is recorded

#### Scenario: A non-compliance Hold does not block anonymisation

- **GIVEN** a Customer whose only active Hold is a non-`compliance` type (e.g. `payment` or `fraud`) and no active `compliance` Hold
- **WHEN** `AnonymiseCustomer` is invoked
- **THEN** anonymisation proceeds (PII overwritten, `anonymised_at` set, `CustomerAnonymised` recorded), and the non-`compliance` Hold's structural blocking state is preserved

#### Scenario: Lifting the blocking compliance Hold unblocks anonymisation

- **GIVEN** a Customer whose anonymisation was blocked by an active `compliance` Hold
- **WHEN** the `compliance` Hold is lifted and `AnonymiseCustomer` is invoked again
- **THEN** anonymisation proceeds and completes

_Source: spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 2 AC-K-J-9a (GDPR right-to-erasure × active-Hold precedence: regulatory Holds block, non-regulatory do not; blocked request leaves the Customer informed; non-regulatory path proceeds with Hold metadata anonymised alongside) · spec/02-prd/Module_K_PRD_v0.3-MVP.md § 8.2 (Stage 6.5 DEC-027 — per-Hold-type anonymisation precedence) · spec/04-decisions/decisions.md DEC-027 (Stage-6.5 clarification — regulatory-retention Holds block) · decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md (canon MVP-DEC-015 — `compliance`-only; no separate `sanctions` Hold type; resolves the frozen-spec DEC-027-vs-PRD contradiction; sanctions-retention via a `compliance` Hold) · openspec/specs/party-registry/spec.md (the *Hold and Sanctions Read-API* requirement — the within-module `PartyComplianceStatusReader` coverage read) · CLAUDE.md invariant 7 (compliance / Hold gates; Holds never auto-lifted)._

### Requirement: Customer Address

Module K SHALL model an **Address** entity (`parties_addresses`) scoped to the Customer (the natural person; the Customer record itself carries no company data and no B2C/B2B discriminator). A Customer MAY have zero or more Addresses (one-to-many, **within-module** — no cross-module Eloquent relationship or model import). Each Address SHALL carry the standard personal address fields (address lines, locality, region, postal code, country) and, for the **company-billing affordance** (DEC-068), **optional** `company_name` and `vat_id` fields — supporting an individual collector who transacts through their own company for fiscal reasons; the Customer remains the natural person. On anonymisation (see *Customer Anonymisation (Right-to-Erasure)*) the Address's **personal fields** (and any `company_name` / `vat_id`) SHALL be overwritten with deterministic placeholders in the **same** operation as the Customer PII overwrite, and the Address row SHALL be **preserved** (never deleted). At launch only **billing** Addresses are modelled; shipping Addresses and the "Address used at purchase" invoice snapshot are downstream concerns (Module C / Module S+E) and are out of this change.

#### Scenario: A Customer has billing Addresses with optional company fields

- **WHEN** a billing Address is created for a Customer with a `company_name` and `vat_id`
- **THEN** the Address is persisted scoped to that Customer, carries the personal address fields plus the optional `company_name`/`vat_id`, and the Customer record itself carries no company data and no B2C/B2B discriminator

#### Scenario: Address personal fields are overwritten on anonymisation, row preserved

- **GIVEN** a Customer with one or more Addresses
- **WHEN** the Customer is anonymised
- **THEN** each Address's personal fields (and any `company_name`/`vat_id`) are overwritten with deterministic placeholders in the same operation, and every Address row survives (none is deleted)

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (company-billing affordance at Address level — optional company name + VAT id; Customer stays the natural person; no B2C/B2B discriminator), § 8.2 (Address records scoped to the Customer have their personal fields overwritten on anonymisation), § 16 (Module K stores company-billing fields on Address; Module E reads them for invoicing) · spec/04-decisions/decisions.md DEC-068 (B2B dropped at Customer level; company-billing preserved at Address — `BillingAddress` with optional `company_name` + `vat_id`) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md § 6.6 AC-K-XM-25 (company-billing fields exist on Address; Customer carries no B2C/B2B discriminator) · CLAUDE.md invariant 10 (module boundaries — within-module Eloquent only)._

### Requirement: Customer Data Export

Module K SHALL provide a minimal, synchronous `ExportCustomerData` operator action satisfying the GDPR right of access / data portability (canon `J-9b`). Given a Customer, the action SHALL assemble a structured **in-memory** payload containing the Customer's personal data and a **manifest of references** (by id) to the retained transactional history (the Customer's Profiles, and the downstream Order / Voucher / Invoice references as those exist), and SHALL return it to the operator **without persisting any file**. The export SHALL be **read-only**: it SHALL NOT mutate the Customer, SHALL NOT emit a domain event, and SHALL leave no durable artifact. For an **anonymised** Customer the export SHALL reflect the anonymised (placeholder) PII, not the original data.

#### Scenario: Export assembles PII plus a transactional-history manifest, read-only

- **WHEN** `ExportCustomerData` is invoked on a Customer with Profiles
- **THEN** it returns a structured in-memory payload containing the Customer's personal data and a manifest referencing the Customer's transactional history by id, and the Customer is unchanged

#### Scenario: Export persists nothing and emits no event

- **WHEN** `ExportCustomerData` is invoked
- **THEN** no file or durable artifact is written and no domain event is recorded (a read-only assembly)

#### Scenario: Export of an anonymised Customer returns placeholder PII

- **GIVEN** an anonymised Customer
- **WHEN** `ExportCustomerData` is invoked
- **THEN** the returned payload reflects the anonymised placeholder PII, not the original personal data

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 12 (right of access / data portability — a Customer can request export of their personal data + transactional history in a standard format; operationally executed, "not modelled as a state machine") · spec/04-decisions/decisions.md DEC-027 (data-subject rights include portability) · docs/validation/Module_K_Verdict_v0.3-MVP.md § Canon Overlay (`J-9b` data-export, NEW — no committed canon definition exists) · decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md (canon J-9b scoped minimal / synchronous / in-memory to satisfy the compliance narrative without inventing an async/document pipeline or tripping the undecided object-storage ADR — the mechanism is the design decision recorded there)._

## MODIFIED Requirements

### Requirement: Customer Suspension and Closure

The Customer SHALL transition `active → suspended` via `SuspendCustomer`, `suspended → active` via `ReactivateCustomer`, and `active | suspended → closed` via `CloseCustomer` — each the sole writer of the Customer `status` for its transition, running inside one `DB::transaction` against a transaction-locked re-read, recording (respectively) a `CustomerSuspended`, `CustomerReactivated`, or `CustomerClosed` event in the same transaction. Suspension SHALL be **explicit** — manual (operator) or via the Hold coupling — and SHALL NOT be automatically driven by Profile state changes or by a KYC/sanctions verdict (the status FSM is independent of the compliance FSMs — § 9.4; AC-K-BR-Customer-1).

`SuspendCustomer` SHALL **cascade** to the Customer's Profiles: in the same transaction it SHALL transition every Profile currently in `Active` to `Suspended` (recording one `ProfileSuspended` per Profile — § 15.1 *"Cascades to all the Customer's Profiles"*); non-`Active` Profiles are skipped (the FSM has only `Active → Suspended`; the Customer-scope Hold blocks them logically via the read-API). `ReactivateCustomer` SHALL cascade-restore every Profile currently in `Suspended` to `Active` (recording `ProfileReactivated`) **iff** that Profile is no longer covered by any active Hold (a Profile retaining its own active Hold — or under a Customer that retains another active Hold — stays `Suspended`). `CloseCustomer` SHALL **not** cascade to Profiles — § 15.1 `CustomerClosed` names no cascade (contrast `CustomerSuspended`); `closed` is **terminal** and is **orthogonal to** anonymisation (a `closed` Customer stays admin-queryable until separately anonymised — AC-K-BR-Customer-2; **anonymisation is an independent operation, implemented by the *Customer Anonymisation (Right-to-Erasure)* requirement** — a `closed` Customer MAY be anonymised and remains queryable only as an opaque identifier thereafter). Every transition SHALL be **from-state guarded**: a `SuspendCustomer` on a Customer not in `active`, a `ReactivateCustomer` not in `suspended`, or a `CloseCustomer` not in `active`/`suspended`, SHALL be rejected with a localized `IllegalCustomerTransition`, leaving status, the cascade and the event log unchanged.

#### Scenario: Suspend an active Customer cascades to its active Profiles

- **GIVEN** a Customer in `active` with two Profiles in `Active` and one in `Lapsed`
- **WHEN** `SuspendCustomer` is invoked
- **THEN** the Customer's `status` becomes `suspended` and one `CustomerSuspended` event is recorded, AND each of the two `Active` Profiles becomes `Suspended` with one `ProfileSuspended` each, AND the `Lapsed` Profile is unchanged

#### Scenario: Restore a Customer reactivates only the Profiles no longer covered by a Hold

- **GIVEN** a `suspended` Customer whose suspension cascaded two Profiles to `Suspended`, one of which also carries its own active Profile-scope Hold
- **WHEN** `ReactivateCustomer` is invoked (the Customer-scope Hold having been lifted)
- **THEN** the Customer's `status` becomes `active` with one `CustomerReactivated` event, AND the Profile with no remaining Hold returns to `Active` with one `ProfileReactivated`, AND the Profile still carrying its own active Hold stays `Suspended`

#### Scenario: Close a Customer is terminal and does not cascade to Profiles

- **WHEN** `CloseCustomer` is invoked on a Customer in `active` or `suspended`
- **THEN** the Customer's `status` becomes `closed`, one `CustomerClosed` event is recorded, and no Profile is transitioned by the Action (closure names no cascade)

#### Scenario: Illegal Customer status transition is rejected

- **WHEN** `SuspendCustomer` is invoked on a Customer not in `active`, or `ReactivateCustomer` not in `suspended`, or `CloseCustomer` not in `active`/`suspended`
- **THEN** an `IllegalCustomerTransition` is raised and the Customer `status` (and any Profiles) are unchanged

_Source: spec/02-prd/Module_K_PRD_v0.3-MVP.md § 4.1 (Customer FSM `pending → active → suspended → closed`; suspension explicit on cross-cutting Holds; closed terminal, orthogonal to anonymisation), § 10.1 (Customer-level suspension blocks all the Customer's Profiles; restore on lift), § 14.2 BR-K-Customer-1/2 (suspension explicit, not auto-driven by Profile state; closed and anonymised orthogonal), § 15.1 (`CustomerSuspended` cascades to all Profiles; `CustomerReactivated`; `CustomerClosed` terminal, names no cascade) · spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md AC-K-FSM-1 (Customer FSM + the five events), AC-K-BR-Customer-1 (suspension explicit, not auto-driven), AC-K-BR-Customer-2 (closed queryable until anonymised; independent operations), AC-K-BR-Hold-3 (a Customer-level Hold blocks every Profile), AC-K-EVT-1 (`CustomerSuspended`/`CustomerReactivated`/`CustomerClosed`) · decisions/2026-06-19-hold-status-coupling.md (the cascade + coverage-recompute restore), decisions/2026-06-12-event-substrate-and-audit-store.md (transactional recording) · openspec/specs/party-registry/spec.md (the *Customer Anonymisation (Right-to-Erasure)* requirement — anonymisation is now implemented as an independent operation, closing this requirement's former "out of scope" note)._
