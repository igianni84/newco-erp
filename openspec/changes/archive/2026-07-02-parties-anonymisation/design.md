## Context

The GDPR right-to-erasure floor (Module K `AC-K-J-9` / `J-9a` / `FSM-16` / `BR-Customer-2`) is 100% unbuilt: no anonymise action, no `anonymised_at`, no PII-overwrite, no Address entity (Module K Verdict 2026-07-01). Only the erasure **seam** exists and is documented as reserved for exactly this change:

- `domain_events` is PII-free by design; `audit_records` has **nullable** `before`/`after` and the immutability-triggers migration (`2026_06_12_000004`) permits **one** mutation — a `before`/`after`-only UPDATE — as the GDPR redaction path, with a PG `redactor` role reserved *"used solely by Module K's GDPR erasure job (a later change)"* (`decisions/2026-06-12-event-substrate-and-audit-store.md`, `openspec/specs/event-substrate/spec.md`).
- Customer PII (`name`, `email`, `phone`, `date_of_birth`) is confined to the `parties_customers` module table; `email` is **UNIQUE**. `CloseCustomer` documents `closed` ≠ anonymised as a deferred `parties-anonymisation` seam.
- Holds are a within-module registry read through `PartyComplianceStatusReader` / `DatabaseComplianceStatusReader` (returns `ComplianceStatus(sanctionsStatus, activeHoldTypes)`); **8** `HoldType` cases post-RM-04; there is **no** `sanctions` Hold type — sanctions is the separate `sanctions_status` FSM on the Customer.

This change completes the flow the RM-09 identity-auth / substrate ADRs were corrected to promise. It is Phase-2, dependency-free (no other backend module gates it).

**Two frozen-spec problems this change must resolve, not inherit:**
1. **The Hold block-set is self-contradictory in our own frozen spec** — greenfield `decisions.md` DEC-027 Stage-6.5 lists `compliance` as *non*-blocking (only `sanctions` blocks); PRD §8.2 + `AC-K-J-9a` list `compliance` **and** sanctions-OFAC as blocking. Canon `MVP-DEC-015` collapses both to `compliance`-only (Giovanni-confirmed 2026-07-02).
2. **Address is under-specified in the frozen PRD** — §4 names "eight entities" and Address is not one; §2 personas even hang address state off Account. `DEC-068` is the authoritative decision: an Address entity in Module K, `BillingAddress` rows with optional `company_name` + `vat_id`, attached to the Customer-as-natural-person. This change follows DEC-068, not the looser persona prose.

## Goals / Non-Goals

**Goals:**
- `AnonymiseCustomer`: transactional PII overwrite-in-place (Customer + its Addresses) with deterministic per-Customer-unique placeholders, `anonymised_at`, audit-records redaction, PII-free `CustomerAnonymised` event; keyed history preserved; orthogonal to the status FSM.
- Anonymisation Hold-precedence gate: **only `compliance` blocks** (canon MVP-DEC-015).
- The Address entity (`parties_addresses`), billing-capable, scoped to Customer, anonymisation-aware.
- Minimal synchronous `ExportCustomerData` (canon J-9b) — in-memory, no persistence.
- Operator console: `Anonymise` + `Export`, visibility-gated.
- Adopt canon MVP-DEC-015 via a mini-ADR (also records the J-9b scope + the `CustomerAnonymised` event addition).

**Non-Goals** (see proposal slice-boundary table):
- HubSpot PII-removal sync (unbuilt — `CustomerAnonymised` is its seam); 10-yr retention enforcement (Module E); un-anonymise flow; shipping Addresses + purchase-time invoice snapshot; the Hold 6→8 truth-spec sync (RM-04 debt — incidental).

## Decisions

### D1 — `AnonymiseCustomer` is one transactional overwrite-in-place action; placeholders are deterministic + id-keyed
A single `AnonymiseCustomer` Action mirrors the module's existing transition-Action shape (one `DB::transaction`, `lockAndRefresh` on the Customer, operator floor). It overwrites the Customer PII and every scoped Address's personal fields, sets `anonymised_at`, redacts audit (D6), and records `CustomerAnonymised` (D3). **Placeholders are deterministic and derived from the Customer id** — e.g. `email → anonymised+{id}@anonymised.invalid`, `name → "Anonymised Customer {id}"`, `phone → NULL`, `date_of_birth → NULL` — so (a) the **UNIQUE email invariant is preserved** (the id makes each placeholder unique), and (b) the operation is reproducible/testable (never `random`/`faker`). A small `AnonymisedPlaceholders` value-object/helper centralises the derivation. _Alternative rejected:_ a status enum value `anonymised` (contradicts FSM-16 orthogonality — anonymisation is a flag+timestamp, not a status); random placeholders (non-deterministic, and a random email risks a unique collision).

### D2 — Hold-precedence = canon MVP-DEC-015 `compliance`-only, read via the existing compliance reader
The gate reads the Customer's active Hold coverage through the within-module `PartyComplianceStatusReader::forCustomer(...)` (never the `Hold` Eloquent model directly — the no-model-leak boundary law) and **blocks iff an active `compliance` Hold covers the Customer**. No other type blocks; the 8-type set is handled implicitly (only one type gates). This adopts canon MVP-DEC-015 and simultaneously fixes the frozen-spec contradiction (Context #1). The **sanctions-retention** case is handled by Compliance placing a `compliance` Hold — there is no `sanctions` Hold type to key on, and the sanctions FSM (`sanctions_status`) is deliberately *not* wired into this gate (canon dropped the sanctions-OFAC block; a sanctioned customer requiring retention gets a `compliance` Hold). _Alternative rejected:_ keep the frozen 2-category rule (`compliance` + sanctions) — there is no `sanctions` Hold to check, so it would need bespoke wiring to `sanctions_status`, and it contradicts canon; keep greenfield DEC-027 (`sanctions`-only, compliance non-blocking) — oldest, contradicts PRD + canon.

### D3 — Add a PII-free `CustomerAnonymised` event (over the frozen spec's event-free anonymisation)
The frozen §15.1 Customer event family names **no** anonymisation event (anonymisation is "recorded" as a state write). We add `CustomerAnonymised` (payload = Customer id + `anonymised_at`, PII-free) because: (a) it is the consistent module pattern (every lifecycle change emits a PII-free event); (b) it is the clean **seam** for the deferred HubSpot PII-removal sync (AC-K-J-9 "in the same operation") and any future consumer; (c) being PII-free it respects the event-substrate discipline. Recorded in the mini-ADR as an addition-over-silence. _Alternative rejected:_ event-free (frozen literal) — leaves HubSpot removal with no seam and breaks the module's emit-on-state-change pattern.

### D4 — Address is a within-module entity, Customer `hasMany`, billing-only at launch
`parties_addresses` with an FK to `parties_customers`, standard personal address fields + optional `company_name` + `vat_id` (DEC-068). `Customer hasMany Address` / `Address belongsTo Customer` — **within Module K only** (invariant 10; no cross-module relationship). A thin `CreateCustomerAddress` action creates rows (named `Create*` so it stays out of the non-`Create*` Action allow-lists — see Risks). Anonymisation overwrites the Address personal fields (D1) in the same transaction, preserving the row. Shipping + purchase-time snapshot deferred (frozen Module K names only billing). _Alternative rejected:_ address fields on Account (the §2 persona prose) — DEC-068 is the authoritative decision and puts them on a Customer-scoped Address entity; hanging PII off Account would fork the erasure target.

### D5 — `ExportCustomerData` is minimal, synchronous, in-memory (canon J-9b)
Canon J-9b has **no committed definition anywhere** (only a verdict parenthetical); the frozen PRD §12 calls export "operationally executed … not modelled as a state machine." So the action assembles an **in-memory** structured payload (Customer PII + a by-id manifest of transactional history) and returns it — **no file persistence, no event, no mutation**. This satisfies the compliance narrative + the tracker Done-when without inventing an async/document pipeline and, crucially, **without tripping the undecided object-storage-for-documents ADR** (gate = INV1 issuance). _Alternative rejected:_ a persisted downloadable export (invents shape with no spec; trips the object-storage ADR early); deferring J-9b entirely (the tracker's Done-when lists export; Giovanni chose minimal-now 2026-07-02).

### D6 — Audit redaction is investigation-first; the redaction path is before/after → NULL
The action nulls the Customer's own `audit_records.before`/`after` (the sole mutation the immutability triggers permit). **Investigate first** (no assumptions): confirm which Parties `audit_records` rows actually carry Customer PII in their snapshots — if Parties writes only PII-free snapshots today, this leg is a **documented no-op** that still wires the capability (so it is correct the day a PII-bearing snapshot lands). Do not fabricate a `redactor` DB-role dependency in tests (SQLite has no such role; the trigger permits the before/after-only UPDATE on both engines). _Alternative rejected:_ deleting audit rows (the immutability trigger forbids DELETE; and it would destroy the retained trail).

### D7 — Adopt canon MVP-DEC-015 via a mini-ADR (the standing rule)
MVP-DEC-015 is canon absent from our frozen `spec/` (stops at MVP-DEC-007). Per `lessons.md` 2026-07-02 (confirmed 3× — RM-04/DEC-008, RM-10/DEC-018, RM-24/DEC-023), adopting a canon-DEC absent from the frozen spec **always** earns a mini-ADR, regardless of the tracker's advisory "ADR? —". The ADR (`decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md`) records: (a) the `compliance`-only block-set + the resolution of the frozen-spec DEC-027-vs-PRD contradiction; (b) the sanctions-retention-via-`compliance`-Hold reconciliation; (c) the J-9b minimal-sync scope (D5); (d) the `CustomerAnonymised` event addition (D3). Mirror the shape of `decisions/2026-07-02-adopt-dec-018-clubcredit-accrued.md`; add the `decisions/INDEX.md` row. _Alternative rejected:_ no ADR (tracker "—") — invariant #11 bans editing `spec/`, so `decisions/` is the only home for the code↔canon divergence.

## Risks / Trade-offs

- **New non-`Create*` Actions red the exhaustive Action allow-list** → `AnonymiseCustomer` and `ExportCustomerData` are non-`Create*` Actions under `app/Modules/Parties/Actions/`. `lessons.md` 2026-06-23: `SupplyLifecycleChainTest` globs every non-`Create*` Action and asserts an exact `toEqualCanonicalizing` set — an unregistered new Action reds the full suite. **Mitigation:** grep `tests/` for any glob over `Parties/Actions/` / `toEqualCanonicalizing`; register both new Actions there. `CreateCustomerAddress` is named `Create*` so it is excluded — keep it so.
- **UNIQUE-email placeholder collision** → anonymising two Customers must not collide on the unique `email` index. **Mitigation:** the placeholder is id-keyed (D1); add a test that anonymises two Customers and asserts both succeed with distinct emails.
- **Postgres-truthful migration** → `parties_addresses` + `anonymised_at` must be Postgres-truthful yet SQLite-compatible (tests run `:memory:`), additive-nullable, no PG extension (`CLAUDE.md` stack rules; `decisions/2026-06-12-production-db-engine.md`). Use `timestampTz` for `anonymised_at`, a plain FK for `customer_id`. **Verify on PG17** at close (F1: `DemoSeeder` TRUNCATE caveat is unrelated here).
- **Audit-redaction may be a no-op today** → if Parties audit snapshots are already PII-free, D6 is a documented no-op. Don't force a fake PII row just to "prove" redaction; assert the mechanism (a before/after-only UPDATE succeeds under the trigger) and document the finding.
- **Console visibility-gated actions are undrivable in tests** (`lessons.md` 2026-06-23/24) → an `Anonymise`/`Export` action whose `->visible()` is false can't be driven via Filament test helpers, and isolated mounts prove definition not render. **Mitigation:** test the **visibility** with `assertActionVisible`/`assertActionHidden` (e.g. `Anonymise` hidden once `anonymised_at` is set, or when a `compliance` Hold blocks) and the domain effect via the Action directly; live-verify the button in-browser (dev-browser) if render-suppression is suspected.
- **Pest + PHPStan-max traps (`lessons.md`, many)** → one `->not` per `expect()` chain (re-anchor with `->and`); array membership via `expect(in_array(...,true))->toBeTrue()`; run the FULL suite + PHPStan via `php -d memory_limit=-1 vendor/bin/{pest,phpstan analyse}` (bare `artisan test` OOMs at 128M).
- **Orthogonality regression** → the action must NOT write `status` or record a `CustomerClosed`/`CustomerReactivated`. Add a scenario: anonymise a `closed` Customer, assert `status` still `closed` and no status event.

## Migration Plan

Two **additive** migrations (Postgres-truthful, SQLite-compatible, no PG extension, no backfill):
1. `add_anonymised_at_to_parties_customers` — nullable `timestampTz('anonymised_at')`.
2. `create_parties_addresses_table` — `id`, `customer_id` FK → `parties_customers`, personal address fields (lines, locality, region, postal code, country), nullable `company_name`, nullable `vat_id`, `timestampsTz`.

Deploy is code + additive migrations; rollback is a revert + drop (no data written pre-launch). No open-ADR gate is tripped (export in-memory; event synchronous).

## Open Questions

- **Placeholder token spelling** — `anonymised+{id}@anonymised.invalid` vs a reserved domain. Default to `.invalid` (RFC 6761 reserved, guaranteed non-routable); confirm at review if a different convention is preferred.
- **Export payload shape** — array/DTO vs a `resources/`-style structure. MVP returns a plain structured array (id-manifest for downstream refs); a richer machine-readable format is a J-9b follow-up if Paolo/canon defines one.
- **Address personal-field set** — the minimal set (lines/locality/region/postal/country + optional company/VAT) vs a richer schema. Default minimal; extend when Module S/E invoicing consumes it.
