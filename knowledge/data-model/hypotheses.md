# Data-Model — Hypotheses (test when possible; Confirmations: N/3)

> An observation becomes a hypothesis once it has a plausible mechanism. Three dated confirmations in DIFFERENT changes promote it to `rules.md`; a contradiction demotes it back here. Mechanics: `.claude/CLAUDE.md` → Knowledge System.

## Spine create-entity template: one new evented entity = migration + model + factory + `*Created` event + `Create*` action (record-in-transaction)

**Hypothesis.** Standing up a new evented persistence entity follows a fixed skeleton, every part co-moving:
1. **Migration** `{module}_x` — columns + a driver-guarded PG `CHECK` from the enum's `::cases()` (see `rules.md`) + explicit short index names.
2. **Model** `Models\X` with `$guarded = []` (the Action is the sole writer) and a **typed** `protected newFactory(): XFactory` — factories live off the `App\Models` PSR-4 convention, so Larastan infers `mixed` without the explicit return type.
3. **Factory** `Database\Factories\{Module}\XFactory` — a pure fixture that bypasses the Action (records **no** event; never use it to prove an evented path).
4. **Event** `Events\XCreated` — untyped `const NAME` / `const ENTITY_TYPE` + a static **PII-free** `payload($x)`.
5. **Action** `Actions\CreateX` — wraps `Model::create()` **and** `$recorder->record(...)` in ONE `DB::transaction` (the recorder's level-0 `NotInTransactionException` guard makes write+emit atomic).

**Confirmations: 2/3** (need 1 more distinct change).
- 2026-06-15 `catalog-product-spine` — origin; defined task 2.1 → repeated across 7 entities (`catalog_*` + `Models\*` + `Database\Factories\Catalog\*Factory` + `*Created` + `Create*`).
- 2026-06-15 `parties-core` — named the template explicitly and reused it across Producer / Club / ProducerAgreement / Customer+Account / Profile / Supplier.
- *(Related, NOT counted: the 2026-06-16 lifecycle changes build a **transition**-action variant on the same `DB::transaction` + `record` shape — a descendant, not a fresh create-entity spine. The next slice that stands up a new create-entity spine is the 3rd confirmation → promote.)*

**Applies to.** Any new evented domain entity in a module slice.

## A NON-evented entity gets a THIN `Create*` action — a single `Model::create()`, no `*Created` event, no `DB::transaction` (`CreateCustomerAddress` is the reference)

**Hypothesis.** The evented spine template above is for entities that emit a `*Created` domain event. An entity that is **not** an inter-module event source — a compliance/minimal-sync record, an audit-only ledger row — takes the *inverse* shape: a thin `Create*` action doing a single `Model::create()`, recording **no** domain event and needing **no** `DB::transaction` (the recorder's level-0 atomicity guard only matters when a write and an `record()` must be atomic; with no emit there is nothing to make atomic). The entity still keeps the persistence half of the spine (`$guarded = []`, a typed `newFactory()`, a fixture factory). Deciding which shape a new entity takes is a design question answered by one fact: *does creating it emit a domain event other modules consume?* If no, thin; if yes, the full evented spine.

**Confirmations: 2/3** (need 1 more distinct change).
- 2026-07-02 `parties-anonymisation` — "Thin `Create*` action = no event, no transaction … A creation Action that records NO domain event and does a single `Model::create()` needs neither a `DB::transaction`… `CreateCustomerAddress` is the reference."
- 2026-07-03 `parties-enhanced-kyc-threshold` — task 4.1 "a THIN `Create*` action mirroring `CreateCustomerAddress` (no event, no transaction)"; the event (`CustomerEnhancedKycReviewRequired`) and the row-create are deliberately **split** across an event-free `CreateComplianceReview` and the `Evaluate…` orchestrator.
- *(Related, NOT counted: 2026-06-23 `club-credit` stands up the new `ClubCredit` entity audit-only — `IssueClubCredit` records no domain event — but it is an `Issue*` domain-verb writer with an `audit_records` write, not the thin `Create*` shape; a cousin, not a third confirmation.)*

**Applies to.** Every new persistence entity that is not an inter-module event source — compliance/minimal-sync/address-style records, and audit-only ledgers. Pairs with the evented spine hypothesis above: the two are the two halves of "how do I stand up a new entity?".
