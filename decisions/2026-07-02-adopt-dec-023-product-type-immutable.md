---
type: decision
status: active
date: 2026-07-02
---

## Decision: adopt canon **DEC-023** locally — a Product Master's Product Type is **fixed at creation and immutable thereafter** (BR-Identity-5), enforced by an explicit model-level guard

Canon criterion **`AC-0-BR-Identity-5`** (MVP-DEC-023) is adopted: a Product Master's `product_type` is set once, at creation, and can never be edited — the remedy for a wrong type is **retire + re-register**, never an in-place type-edit. Precise scope:

- **A `product_type` immutability guard on `ProductMaster`.** Enforced at the model's `updating` event ({@see `ProductMaster::booted()`}): if `product_type` is dirty on an existing row, throw the new localized `ProductTypeImmutable`. It fires on UPDATE only (creation runs through `creating`, so the initial set is free) and keys on `isDirty('product_type')`, so a lifecycle transition — which dirties only `lifecycle_state` — passes untouched.
- **`product_type` only.** `name`/identity re-versioning is a *different* concern (BR-Audit-1) owned by **RM-14**; this guard does not touch it.
- **Zero behaviour change to any existing path.** Nothing in the build edits `product_type` today (no update Action; the Filament resource is read-only; `CreateProductMaster` is the sole writer and only at insert). The guard forecloses a path that does not yet exist — it makes an incidental invariant *enforced*.

## Context: why this came up

`AC-0-BR-Identity-5` is a **NEW canon criterion absent from our frozen `spec/`** (which stops at MVP-DEC-007 — the same escalation-asymmetry as DEC-008/RM-04 and DEC-018/RM-10: the canon corrections DEC-008..023 never flowed into our snapshot; team-memory `spec-divergence-from-cmless-documentation`). The 2026-07-01 Module 0 validation flagged it: `docs/validation/Module_0_Verdict_v0.3-MVP.md` line 103 — "Product Type immutable post-creation (retire + re-register, never type-edit)", noting we would *likely Pass it structurally* but should add an explicit guard-test. Tracked as **RM-24**.

Structurally we already satisfied it: `WINE` is the only launch type (`CreateProductMaster` fail-closes any other token), there is no update Action, and `ProductMasterResource` is read-only (a PHPStan rule bans Eloquent writes in the console). **But** `product_type` is a **real, mutable column** on a single table with `$guarded = []` — so the immutability rested on the *absence of a writer* (a discipline a future update path could silently break), not on an enforced invariant. This ADR + guard converts incidental → enforced. This is the crucial contrast with Module K's `party_type`, which is immutable **by construction** — Customer and Supplier are distinct `parties_*` tables, so BR-K-Identity-5 needs no runtime guard ([[2026-06-15-party-type-marker-on-subtype]]). `product_type` is a discriminator *within one table*, so it must be guarded, not merely modelled.

## Alternatives considered

- **Model `updating` guard (chosen).** The only chokepoint that catches EVERY mutation path — `update()` / `save()` / mass-assign / tinker — precisely because there is no single update-Action to guard and `$guarded = []` leaves mass-assignment open. Minimal (one `booted()` hook + one exception), and it is a persistence invariant (this column never changes post-insert), consistent with the model's "persistence-only, sole-writer" discipline.
- **DB-level immutability (Postgres trigger / generated column).** Rejected — PG-only, untestable on the SQLite test engine, and over-engineered for an S item; against the "Postgres-truthful **and** SQLite-compatible, no PG extensions at launch" grain ([[2026-06-12-production-db-engine]]). The application guard is the launch floor; a DB backstop can be added later if ever needed.
- **Guard inside an Update Action.** Rejected — there is no update Action to host it (and adding one purely to reject would invert the intent).
- **Rely on the structural absence + a test only, no guard.** Rejected — `product_type` is genuinely mutable (real column, `$guarded = []`); a future 2nd-type Update path or a raw mass-assign could flip it, and the item explicitly asks for an *explicit* guard. A test with no guard would assert only today's incidental behaviour.
- **A generic immutable-field trait for all seven spine entities.** Rejected — YAGNI. RM-24 is scoped to `product_type`; the sibling identity-immutability concerns (name re-versioning) are separate items (RM-14). Generalise if a real second case appears.
- **Skip the mini-ADR — a note would do (the tracker §3 row says "ADR? —").** Rejected. DEC-023 is a **canon decision absent from our frozen spec**; adopting it is the same class as DEC-008 ([[2026-07-01-adopt-dec-008-hold-types-8]]) and DEC-018 ([[2026-07-02-adopt-dec-018-clubcredit-accrued]]), each of which got a mini-ADR — a uniform, auditable adoption trail matters when we reconcile with c-mless. **Honest nuance recorded here:** unlike those two, this adoption changes **no behaviour** — it does not *diverge from* a spec position (RM-04: spec self-contradicted; RM-10: spec coherent-and-reversed) but *fills a silence* our own code + event docblocks already treated as true ("`name`, `product_type` … the immutable creation record"). It is codification, not divergence — but a canon adoption nonetheless, so it is traced.

## Trade-offs accepted

- **A first model-layer guard on the spine models** (previously purely persistence-only) — accepted: it is a persistence-integrity invariant, not business logic, and the model event is the only *complete* chokepoint given no Action-layer writer for updates.
- **The "reject a real change" path is not expressible through the enum API today** — `WINE` is the only case, so an unsupported token throws `ValueError` at set-time (framework, before the guard). The test drives a differing *persisted* value via `setRawAttributes` to exercise the guard's chokepoint directly — future-proofing for the day the category-neutral design adds a 2nd Product Type (exactly the scenario BR-Identity-5 exists for).
- **Scope limited to `product_type`** — `name`/identity re-versioning (BR-Audit-1) is deferred to RM-14; not folded in (scope discipline).

## References

- Canon: **MVP-DEC-023** / `AC-0-BR-Identity-5` (c-mless/documentation register; not in our frozen snapshot, which stops at DEC-007) — Product Type fixed at creation, retire + re-register, never a type-edit.
- Validation: `docs/validation/Module_0_Verdict_v0.3-MVP.md` line 103 (canon overlay — the NEW criterion); `docs/validation/Remediation_Tracker.md` **RM-24**.
- Spec / design: `spec/02-prd/Module_0_PRD_v0.3-MVP.md` §3.9 / §16 (category-neutral core; a new type adds its own attribute table); [[2026-06-14-catalog-category-neutral-representation]] (where `product_type` selects the per-type attribute set, variant dimension and identity key — design D2, the reason re-typing is illegal).
- Related: [[2026-06-15-party-type-marker-on-subtype]] (Module K's `party_type`, immutable **by construction** — the contrast that motivates a runtime guard here); [[2026-07-01-adopt-dec-008-hold-types-8]] + [[2026-07-02-adopt-dec-018-clubcredit-accrued]] (the two prior canon adoptions — same escalation-asymmetry, same mini-ADR discipline); team-memory `spec-divergence-from-cmless-documentation`.
- Implemented by: RM-24 — `app/Modules/Catalog/Exceptions/ProductTypeImmutable.php`, `ProductMaster::booted()` guard, `lang/en/catalog.php` `immutable_product_type`, 2 tests in `tests/Feature/Modules/Catalog/ProductMasterTest.php`. Suite 1769/1769, PHPStan/Pint clean.
