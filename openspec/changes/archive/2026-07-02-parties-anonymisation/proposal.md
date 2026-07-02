## Why

NewCo's GDPR **right-to-erasure floor is 100% absent** — no anonymise action, no `anonymised_at`, no PII-overwrite, and **no Address entity at all** (Module K Verdict 2026-07-01, launch-critical gap #1; `AC-K-J-9` / `J-9a` / `FSM-16` / `BR-Customer-2` all Fail/Gap, 🔴 floor). What *is* built is only the erasure **seam** — PII-free `domain_events`; the `audit_records` `before`/`after` redaction path behind the structural-immutability triggers; Customer PII confined to the module table — and the PG `redactor` role is documented as reserved *"for Module K's GDPR erasure job (a later change)."* **This is that change** (RM-01, the Round-2 P0 compliance-floor headline). Completing it is also what makes the RM-09 identity-auth / event-substrate ADR erasure claim fully truthful.

## What Changes

- **Add the `AnonymiseCustomer` operator action** (Module K) — in one transaction it overwrites the Customer's PII (`name`, `email`, `phone`, `date_of_birth`) and the personal fields of the Customer's Address records with **deterministic, per-Customer-unique placeholders**, sets `anonymised_at`, preserves all keyed transactional history (Profile / Order / Voucher / Invoice survive; vouchers stay valid), and **redacts the Customer's own `audit_records` PII** via the reserved redaction path. `AC-K-J-9`.
- **Add the Address entity** (`parties_addresses`) — billing-capable, scoped to the Customer (natural person), with optional `company_name` + `vat_id` (DEC-068); its personal fields are overwritten during anonymisation. `AC-K-XM-25`.
- **Record `anonymised_at` as a flag+timestamp orthogonal to the Customer status FSM** — a `closed` Customer MAY be anonymised and an anonymised Customer keeps its status; the two are **independent operations** (`AC-K-FSM-16` / `BR-K-Customer-2`). This makes the truth spec's current "anonymisation is out of scope" clause obsolete.
- **BREAKING (canon adoption): enforce anonymisation Hold-precedence — only a `compliance` Hold blocks** anonymisation; **no other Hold type** blocks (`AC-K-J-9a`). This **adopts canon MVP-DEC-015** (absent from our frozen `spec/`, which stops at `MVP-DEC-007`) and **resolves a contradiction inside our own frozen spec**: greenfield `DEC-027` says only `sanctions` blocks (compliance *non*-blocking); PRD §8.2 / `AC-K-J-9a` say `compliance` + sanctions-OFAC block. Canon collapses both to `compliance`-only — which also matches **code reality** (there is no `sanctions` Hold type; sanctions is the separate `sanctions_status` FSM). A sanctioned Customer's retention is enforced by Compliance placing a `compliance` Hold. Per the standing rule (`lessons.md` 2026-07-02, confirmed 3× via RM-04/10/24) this earns a **mini-ADR**, regardless of the tracker's advisory "ADR? —".
- **Record a PII-free `CustomerAnonymised` domain event** (id + `anonymised_at` only) as the seam for downstream PII removal (HubSpot, unbuilt) — a deliberate addition over the frozen spec's event-free anonymisation, justified in the mini-ADR.
- **Add a minimal, synchronous `ExportCustomerData` operator action (canon J-9b)** — assembles the Customer's PII + a manifest of retained transactional-record references into a structured **in-memory** payload (no file persistence). Canon J-9b has **no committed definition anywhere** (only a verdict parenthetical); scoped minimal to satisfy the compliance narrative + the tracker's Done-when without inventing an async/document pipeline or tripping the (undecided) object-storage ADR.
- **Operator console:** surface `Anonymise` + `Export` on the Customer console (visibility-gated).

### Slice boundary — deliberately NOT in this change

| Deferred concern | Future change / owner | Why not here |
|---|---|---|
| **HubSpot PII-removal sync** ("in the same operation" — AC-K-J-9) | HubSpot integration change (Phase 2) | HubSpot is unbuilt; RM-01 emits the PII-free `CustomerAnonymised` seam the sync will consume. |
| **10-year retention enforcement** | Module E / operational policy | PRD §8.2 / §12: retention is a Module-E/operational concern; RM-01 only **preserves** (never deletes) the keyed history so E's retention can operate. |
| **Un-anonymise flow** | — (not required at launch) | The PRD asserts orthogonality/reversibility but specs no un-anonymise flow. |
| **Shipping addresses + "Address used at purchase" invoice snapshot** | Module C (fulfilment) / Module S+E (invoicing) | Frozen Module K names only a **billing** Address; shipping + purchase-time snapshot are downstream. |
| **Hold enum 6→8 truth-spec sync** | incidental finding (RM-04 debt) | The `Hold Registry` truth-spec requirement still says "six-value" while code is **8** (RM-04 shipped code + mini-ADR but authored no OpenSpec delta). RM-01's Hold-precedence is phrased `compliance`-only (count-independent), so it does **not** depend on the fix — filed as an incidental, not fixed here. |

## Capabilities

### New Capabilities

_(none)_

### Modified Capabilities

- `party-registry`: **ADD** four requirements — `Customer Anonymisation (Right-to-Erasure)`, `Anonymisation Hold Precedence`, `Customer Address`, `Customer Data Export`; **MODIFY** `Customer Suspension and Closure` (its "anonymisation is out of scope" clause becomes a cross-reference now that anonymisation is implemented and the closed/anonymised orthogonality is demonstrable). No new capability spec — these are Customer/Module-K concerns within the existing `party-registry` capability.

## Impact

- **Schema (2 migrations, Postgres-truthful + SQLite-compat, additive, no PG extension):** `anonymised_at` (nullable `timestamptz`) on `parties_customers`; new `parties_addresses` (FK → `parties_customers`; personal address fields + optional `company_name` / `vat_id`).
- **Domain (Module K):** `AnonymiseCustomer`, `ExportCustomerData`, `CreateCustomerAddress` actions; `Address` model (Customer `hasMany`, within-module — no cross-module Eloquent); `CustomerAnonymised` PII-free event; a deterministic-placeholder helper. ⚠️ `AnonymiseCustomer` / `ExportCustomerData` are new **non-`Create*`** Actions → must be registered in any exhaustive Action allow-list test (`SupplyLifecycleChainTest`, `lessons.md` 2026-06-23), or the full suite reds.
- **Audit envelope (invariant 8):** the action nulls the Customer's own `audit_records` `before`/`after` (the sole mutation the immutability triggers permit) — investigation-first: verify which Parties audit rows carry Customer PII before wiring the redaction (may be a documented no-op if Parties audit is already PII-free).
- **Operator console:** `app/Modules/OperatorPanel/Filament/**` — `Anonymise` + `Export` actions, visibility-gated; verify in browser.
- **Docs:** mini-ADR (`decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md`) + `decisions/INDEX.md`; `CONTEXT.md` (add the `Address` canonical term; flip the `parties-anonymisation` seam entry to *implemented here*); localized reason string(s) in `lang/en/parties.php` (invariant 12).
- **No open-ADR gate tripped:** erasure/Address is Phase-2, dependency-free; export is in-memory (no object-storage); `CustomerAnonymised` is recorded synchronously in the action's transaction (no queued consumer). The two ADR-timing risks (async HubSpot-sync → queue-driver ADR; persisted export → object-storage ADR) are only reached by *future* changes, not this one.
