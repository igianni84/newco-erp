---
type: decision
status: active
date: 2026-06-15
---

## Decision: Party-type marker as an immutable enum on each subtype; unified Party Registry deferred

Module K (Parties) models the **party-type marker** as an **immutable backed-enum column** (`party_type`, a `PartyType` enum with cases `customer`, `supplier`, `third_party_owner`) on **each subtype's own table** — `parties_customers.party_type` (always `customer`) and `parties_suppliers.party_type` (always `supplier`) — fixed at row creation and never mutated. Customer and Supplier are **distinct, strongly-typed `parties_*` tables** (and distinct Eloquent classes), not rows in a shared table.

The **unified `parties_parties` registry table**, the **`third_party_owner` subtype entity**, and any **marker overlap** (one real-world party carrying more than one marker) are **deferred** — none is exercised at NewCo launch. The `PartyType` enum nonetheless declares **all three** markers now (the full BR-K-Identity-5 domain), so a future registry slice needs no enum migration. **Producer carries no marker** — it is a standalone registry, not a Party subtype (Module K PRD §4.4).

BR-K-Identity-5 ("the party-type marker is immutable once set; a Customer cannot become a Supplier or vice versa") holds **by construction**: a Customer and a Supplier are different tables and different classes, so there is no single row whose discriminator could be flipped to cross the boundary. The marker column is therefore deliberately redundant with table identity — it is kept as the explicit, self-documenting realisation of BR-K-Identity-5, the literal satisfaction of DEC-067's "Party with `party_type = SUPPLIER`", and the forward seam the deferred registry slice will read.

## Context: why this came up

`spec/04-decisions/decisions.md` **DEC-073** fixes that NewCo PRDs are product-spec-layer documents — they name entities, rules and events and **delegate the physical representation to the dev team**. The party-type marker's representation is exactly such a delegated choice, and the `parties-core` change must pick it before any migration is written, because it shapes the Customer and Supplier tables and the `PartyType` enum.

The spec constraints the representation must honour:

- **DEC-067** (Producer ≠ Supplier separation carries forward verbatim): Module K retains both a **Producer Registry** and a **Party Registry with `party_type = SUPPLIER`**; the Producer ≠ Supplier separation is an *active operational reality* at launch (NewCo deals with Discovery suppliers that are not Producers — e.g. Crurated, DEC-020), not just inheritance discipline.
- **Module K PRD §4.5 (Supplier)**: "Supplier is a Party Registry subtype (the Party entity has subtypes Customer, Supplier, and a dormant Third-Party Owner subtype that is inherited but unused at NewCo launch). Each Supplier carries the legal name, **the immutable party-type marker**, and standard timestamps."
- **Module K PRD §4.4 (Producer)**: "**Producer is NOT a Party subtype.** The Producer entity is a standalone registry in Module K, distinct from the Party Registry." So the marker is a Party-Registry concept and Producer is outside it.
- **BR-K-Identity-5**: "The Party-type marker (Customer / Supplier / dormant Third-Party Owner) is immutable once set. A Customer cannot become a Supplier or vice versa."

The launch reality narrows the choice further. At launch only two of the three markers are produced (`customer`, `supplier`); `third_party_owner` is "inherited but unused" (§4.5). The Producer↔Supplier overlap that a unified registry would naturally express is **not** modelled in Module K at all — it is the **SupplierProducerLink**, an N:N entity **owned by Module D** (DEC-067; Module K PRD §4.5), reached by id across the module boundary, never a marker on a shared Party row. And the two subtypes have nearly disjoint columns: a Customer carries email / name / phone / DOB / preferred currency+locale / originating-club, while a Supplier is minimal (legal name + marker + timestamps; richer state lives in Module D). `parties-core` is the structural identity spine — creation only, no lifecycle, no cross-module reference — so whatever is chosen must be the cheapest representation that satisfies the four constraints above and preserves the seam for a later registry.

## Alternatives considered

1. **Unified `parties_parties` registry table (the literal v17 Party Registry)** — a single table with a `party_type` discriminator and one row per party (Customer, Supplier, dormant Third-Party Owner), subtype-specific columns either co-located (sparse) or split into per-subtype side-tables (single-table / class-table inheritance). The most literal reading of DEC-067's "Party Registry" wording. Rejected for launch: it builds machinery nothing at launch exercises — there is **no** query over "all parties" and **no** marker overlap at launch; the Producer↔Supplier link that would motivate a shared table is a Module-D concern (SupplierProducerLink), not a Module-K row. A shared table is either mostly-null (Customer's eight identity columns are meaningless for a Supplier and vice versa) or needs side-tables anyway, re-introducing the join it was meant to remove. Worst, a single mutable discriminator column **weakens** BR-K-Identity-5: "a Customer cannot become a Supplier" becomes an application-enforced rule guarding an `UPDATE party_type` that the schema permits, rather than a structural impossibility. Keepable as a deferred option if a real cross-party need surfaces.

2. **Marker as an immutable enum on each subtype table (chosen)** — `parties_customers` and `parties_suppliers` are distinct strongly-typed `parties_*` tables, each carrying its own `party_type` enum column fixed at creation. BR-K-Identity-5 holds by construction (distinct tables and classes — no row can cross the marker boundary). The `PartyType` enum declares all three markers now so the deferred registry needs no enum migration. Each table's columns are honest (no mostly-null shared table). The cost is that a future "all parties" query must union the subtype tables — but no such query exists at launch.

3. **No marker column at all — rely on table identity alone** — since each subtype is already its own table, the marker is technically redundant; one could omit it and let the table name *be* the type. Rejected: BR-K-Identity-5 and §4.5 name the marker **explicitly** as a stored attribute, and DEC-067 specifies "Party with `party_type = SUPPLIER`"; dropping the column would also remove the forward seam the deferred unified-registry slice reads, forcing a backfill migration when it lands. The redundancy is accepted deliberately (it is self-documenting and spec-literal) rather than optimised away.

4. **Polymorphic Party with `party_type` as the only shared column** — a thin shared `parties_parties` (id + `party_type` only) plus per-subtype tables holding everything else, joined 1:1. A middle ground between (1) and (2). Rejected for launch: it carries the coordination cost of inheritance (a shared id space, a join on every read, a discriminator to keep in sync) for a registry with two disjoint, low-volume subtypes and no cross-party access path — all cost, no exercised benefit. It remains the natural shape for the deferred `parties-party-registry` slice if one is ever needed, reached additively from (2).

## Reasoning: why this option won

Marker-on-subtype (2) is the cheapest representation that satisfies every constraint and preserves every seam:

- **BR-K-Identity-5 by construction.** A Customer and a Supplier are different tables and different Eloquent classes; "a Customer cannot become a Supplier" is a structural impossibility, not a rule guarding a mutable discriminator. This is a strictly stronger guarantee than any shared-table option offers.
- **YAGNI against the MVP-scope discipline.** The unified registry, the `third_party_owner` entity, and marker overlap are all "inherited but unused at launch" (§4.5) or live in another module (the Producer↔Supplier link is Module D's SupplierProducerLink). Building the shared-table machinery now would add a discriminator, a join, and a sparse/side-table layout that *nothing at launch reads* — the same "category-readiness, not maximal configurability" reasoning that drove the catalog spine's representation ADR (`2026-06-14-catalog-category-neutral-representation`).
- **Honest columns.** Each subtype table holds exactly its own fields — Customer's identity columns, Supplier's minimal three — with no mostly-null shared table and no premature inheritance coordination.
- **The seam is preserved, not closed.** The `PartyType` enum already declares all three markers (the full BR-K-Identity-5 domain), so the deferred `parties-party-registry` slice introduces the unified table **additively** and reads an already-present marker — no enum migration, no backfill of a column that did not exist.
- **Producer stays correctly outside.** §4.4 is explicit that Producer is not a Party; modelling the marker on the subtypes (not on a base every entity inherits) keeps Producer marker-free without a special case.

This also composes cleanly with the closed identity/auth decision (`2026-06-15-identity-auth`): Module K stays pure identity (never a login), and an auth principal references the Module K party **by id** — the subtype table's id — with the marker disambiguating the party kind where needed.

## Trade-offs accepted

- **A future cross-party query must union the subtype tables** rather than scan one table. Accepted: no "all parties" access path exists at launch; the subtype tables are the source of truth, and the deferred registry slice can introduce a unified read/table additively if one is ever needed.
- **The `party_type` column is redundant with table identity** (every `parties_customers` row is `customer`). Accepted deliberately: it makes BR-K-Identity-5 explicit and self-documenting, satisfies DEC-067's "Party with `party_type = SUPPLIER`" wording literally, and is the seam the deferred registry reads — value that outweighs the one redundant, immutable column.
- **The `third_party_owner` marker is defined but homeless** (no entity carries it this slice). Accepted and tracked: revisit when Module B inventory-ownership lands whether it becomes a Party-registry row (the deferred slice) or stays an inventory-side concept; declaring it now keeps the enum stable across that decision.
- **The unified Party Registry is deferred to a named future change** (`parties-party-registry`, only if a later need surfaces). Accepted: the seam (the marker enum, the immutable column) is preserved so the deferral costs no rework, only a later additive table.

## References

- spec/02-prd/Module_K_PRD_v0.3-MVP.md §4.1 (Customer), §4.4 (Producer — "NOT a Party subtype"), §4.5 (Supplier — "Party Registry subtype … the immutable party-type marker"), §13.x BR-K-Identity-5 (immutable party-type marker)
- spec/04-decisions/decisions.md DEC-067 (Producer ≠ Supplier separation; Party Registry with `party_type = SUPPLIER` carries forward) · DEC-073 (PRD = product-spec layer; physical representation delegated to the dev team) · DEC-020 (Crurated as a Discovery supplier — why the minimal Supplier is needed at launch)
- decisions/2026-06-14-catalog-category-neutral-representation.md (the sibling DEC-073-delegated representation choice for the product spine — same "build the launch-exercised shape, keep the rest additive" reasoning)
- decisions/2026-06-15-identity-auth.md (auth principal references the Module K party by id; Module K stays pure identity)
- openspec/changes/parties-core/ (the change this decision shapes — design D1; the Module-D SupplierProducerLink is the overlap home, not a Module-K marker)
