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
