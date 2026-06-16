# Data-Model — Knowledge (observed facts & patterns)

> DDL / migration / schema-shape patterns: enum-backed columns, constraints, indexes, FKs, and the create-entity spine. Promotion lifecycle: this file (observed) → `hypotheses.md` (Confirmations: N/3) → `rules.md` (apply by default). Sibling concerns: cross-engine **test** portability lives in `knowledge/testing/rules.md`; Eloquent/framework-runtime patterns in `knowledge/laravel/`; module-boundary rules in `knowledge/architecture/`.

Domain created **2026-06-16** (Dreaming follow-up) to give DDL/migration/FK-and-index patterns a single home — they had been split between `laravel/` (the enum-`CHECK` rule) and `architecture/` (index naming bundled into the boundary rule). The enum-`CHECK` rule was relocated here verbatim. See `decisions/INDEX.md` and `dreams/2026-06-16.md`.

(Observed-but-not-yet-promoted facts accrue here; confirmed-but-not-yet-rule patterns live in `hypotheses.md`.)
