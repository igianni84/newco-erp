---
type: decision
status: active
date: 2026-07-07
supersedes:
superseded-by:
---

## Decision: Adopt canon **MVP-DEC-010** locally — `ProducerAgreement.settlement_cadence` is a **closed set of three** (`quarterly` default / `monthly` / `semi-annual`), **enforced server-side (domain + DB CHECK, not UI-only)**; `annual` and sub-monthly cadences are **out of set** and the DemoSeeder's `annual` row migrates

> ⚠️ **Number collision — read first.** This is canon **MVP-DEC-010** (`MVP_Decisions_Register_v0.1.md:135`, *"`settlement_cadence` closed to 3 at launch"*), **NOT** the greenfield **`DEC-010`** in our frozen spec (`spec/04-decisions/decisions.md:76`, *"12.5% margin on club sales, 5% revenue share on Discovery"*). Unrelated. Use the full token **`MVP-DEC-010`** everywhere. Same trap the RM-03 ADR flagged for DEC-016, RM-06 for DEC-019, and the sibling [[2026-07-07-adopt-mvp-dec-009-agreement-club-active-scope]] for DEC-009.

We adopt canon **BR-K-Agreement-2** (RM-22) as sharpened by MVP-DEC-010: a ProducerAgreement's `settlement_cadence` is drawn from a **closed set of exactly three** launch cadences — `quarterly` (the default), `monthly`, `semi-annual` — the only producer-settlement cadences the business model contemplates (BMD §3.10; `annual`/sub-monthly named nowhere). The set is **enforced server-side at both API and DB**, not UI-only: a create carrying an out-of-set cadence (`annual`, `weekly`, or a free-text typo such as `quaterly`) is **rejected** before the write.

**What changes (Module K / Parties):**
1. **Free-text → closed set.** `settlement_cadence` today is a free-text nullable `string` column (`…producer_agreements_table.php:59`) with a `@property string|null` (`ProducerAgreement.php:36`) — no cast, no CHECK. It becomes a **string-backed `SettlementCadence` enum** (`quarterly`/`monthly`/`semi_annual`) with a model cast (task 2.1).
2. **Server-side enforcement, two layers.** Domain: `CreateProducerAgreement` validates against the enum set pre-write (task 3.1). DB: the `parties_producer_agreements.settlement_cadence` CHECK is regenerated from `SettlementCadence::cases()` (Postgres-truthful; SQLite skips CHECK, the cast is the floor) — mirrors the `parties_holds` `HoldType` CHECK idiom.
3. **Data consequence — the DemoSeeder `annual` row migrates.** `DemoSeeder.php:296` seeds the `sanguido` producer-wide agreement with `'annual'` — **out of the canon set**, so it would violate the new CHECK/cast. It is migrated to **`semi-annual`** (the nearest in-set cadence for a yearly-ish producer) in task 3.1. `ProducerAgreementFactory.php:44` default `'monthly'` is **in-set** — unchanged.
4. **Casing/form is the dev's call (DEC-073).** Canon's human label is `semi-annual` (hyphen); the backing value is `semi_annual` (underscore) — the literal representation (enum vs lookup, casing, string form) is explicitly delegated to the dev team.
5. **Extensible post-launch by a deliberate code change** — a new cadence is a new enum case + a CHECK-regen migration (a business-approved config addition), never arbitrary free-text.

## Context: why this came up

- **RM-22 / BR-K-Agreement-2**, the settlement-cadence acceptance criterion in the Module K validation batch. `settlement_cadence` today is **free text** (grounding corrected the tracker's "dropdown" premise, `design.md` Context) — a raw write can persist any string, and one already does (`DemoSeeder`'s `annual`).
- **This is an *MVP-scoping delta*, not a canon reversal — and distinct in *kind* from the sibling MVP-DEC-009.** Canon states MVP-DEC-010 is **"No new DEC — bridges DEC-042"**: the **DEC-042 framework** (quarterly default, agreement-configurable — `spec/04-decisions/decisions.md:291`, **which our frozen `spec/` carries**) is *unchanged*; the BMD stays open ("e.g."); launch **closes the open set** as an MVP-scoping delta (no BMD revision). So our frozen spec faithfully carries the *open placeholder* (free-text column + the open BR text), not a *wrong* rule — a completeness/tightening fix, like 009 and unlike the behaviour **inversion** of RM-20. The mechanism differs from 009, though: **009 *added* a missing BR (Agreement-4, erratum of omission); 010 *tightens the value domain* of an existing BR (Agreement-2, already in our frozen spec at `AC-K…:186`).**
- **Why server-side, not UI-only.** The cadence **times money movement** — the per-Producer settlement event in Module E and V1 PO issuance in Module D read it; an out-of-set value would **silently mis-time settlement and corrupt the D19 seam**. So it belongs to the "enforced at API + DB" guard class, not a cosmetic console constraint (AC-K-BR-Agreement-2's negative path asserts rejection *at the API/DB layer*).
- **Escalation-asymmetry / grounding.** Canon sharpened **BR-K-Agreement-2 + AC-K-BR-Agreement-2** on **✅ 2026-06-18 (tech-team Q&A)**. Our frozen `spec/` is pinned in `spec.lock` @ `4f48277` (`source_commit` = *"Module K PRD §9.5/§7.1 … screening launch posture"*), which stops at **MVP-DEC-007** — the frozen `AC-K-BR-Agreement-2:186` carries the **open** version (*"per-Producer overrides are admitted via the agreement"* — no closed set, no server-side clause). Sourced from **LIVE canon `cmless/main @ 360df0b`**, fetched read-only 2026-07-07 (`lessons.md` 2026-07-02 + 2026-07-03; the `git fetch cmless/main` grounding path, [[2026-06-17-spec-synced-from-documentation-repo]]).

## Alternatives considered

- **(A) UI-only enforcement — a `Select` in the console, column stays free-text. ❌ REJECTED (non-compliant).** Canon says the set is enforced *"(API + DB, not UI-only)"* and AC-K-BR-Agreement-2's negative path is *"attempt an out-of-set cadence … assert rejected at the **API/DB layer**"* — a UI-only guard fails that assertion, and lets a direct API/seeder/import write an out-of-set value that silently mis-times settlement (Module E) + PO issuance (Module D). The **DemoSeeder `annual` row is the live proof** a non-UI write already bypasses any console.
- **(B) App-layer validation against a config list, but no DB CHECK. ❌ REJECTED (half-measure).** Canon wants **DB-level** enforcement too (defence in depth — the DB is the last guard against a raw write). A CHECK regenerated from the enum `cases()` is the module idiom (mirrors `parties_holds`) and costs no new machinery.
- **(C) A string-backed `SettlementCadence` enum + cast + a migration CHECK regenerated from `cases()` + server-side validation in `CreateProducerAgreement`. ✅ CHOSEN.** Closed set at both API (enum cast + Action validation) and DB (CHECK), using the module's established enum-backed pattern; DEC-073 delegates the literal form; extensible by adding an enum case + a CHECK-regen migration.

## Reasoning: why C won

- **Spec fidelity is the yardstick.** AC-K-BR-Agreement-2's negative path enforces rejection *at the API/DB layer* — only C (domain + DB CHECK) passes it verbatim; a UI-only select (A) fails the API/DB assertion.
- **Money-timing invariant.** The cadence times settlement (Module E) + PO issuance (Module D); an out-of-set value silently mis-times money movement and corrupts the D19 seam — this is a server-enforced guard, not a UI convenience.
- **Module idiom, minimal surface.** An enum + a CHECK regenerated from `cases()` mirrors the shipped `parties_holds` `HoldType` pattern (Simplicity First) — no new machinery, and the DB CHECK stays derived from a single source of truth.
- **Low blast radius (grounding-confirmed).** The only out-of-set **live** value is the DemoSeeder `annual` row; the factory default `monthly` is in-set. One seeder-row migration is the entire data-migration surface — a new negative test (`weekly`/typo rejected) is the only new test-surface addition (contrast RM-20, which inverts shipped coexistence assertions).

## Trade-offs accepted

- **A one-row data migration (`DemoSeeder` `annual → semi-annual`).** Accepted — it is demo/dev data, `annual` is explicitly out of the canon set, and `semi-annual` is the nearest in-set semantic neighbour of a yearly cadence (the default `quarterly` was the alternative). Recorded here as the change's sole data consequence (task 3.1).
- **Post-launch extension needs a code change, not a runtime toggle.** Adding a cadence = a new enum case + a CHECK-regen migration. Accepted — canon frames a new cadence as *"a deliberate, business-approved config addition, not arbitrary free-text"*, and a code-reviewed enum addition **is** that deliberate gate. (A DB-backed lookup table was the alternative representation; the enum is simpler and DEC-073 leaves the form to us.)
- **Module E's actual settlement-timing read stays a deferred seam (D19).** This change closes + records the cadence value set only. Accepted — AC-K-BR-Agreement-2 itself defers the Module-E consumption (*"verified when Module E lands; D19 deferred — the recorded cadence is the seam"*); `settlement_cadence` stays nullable (the un-set / not-yet-configured agreement is valid).

## References

**Canon (authoritative — `c-mless/documentation` @ `cmless/main` `360df0b`, fetched read-only 2026-07-07; our `spec/` is frozen @ `4f48277`):**
- `MVP_Decisions_Register_v0.1.md:135` — **MVP-DEC-010** (full: closed to 3 at launch — `quarterly` default / `monthly` / `semi-annual`, BMD §3.10; `annual`/sub-monthly named nowhere; enforced **API + DB, not UI-only** because it times Module-E settlement + Module-D PO issuance / the D19 seam; extensible post-launch; representation the dev's call per DEC-073; **DEC-042 framework unchanged, BMD "e.g." stays open — launch closes the set as an MVP-scoping delta, no BMD revision**; placeholder fields still deferred to the 24-month template Q-OQ-9/DEC-058; **No new DEC — bridges DEC-042**; ✅ 2026-06-18 tech-team Q&A).
- `MVP_Decisions_Register_v0.1.md:132` — **MVP-DEC-007** (our frozen `spec/` pin stops here — the escalation-asymmetry; DEC-008..023, incl. this, post-date it).
- `Module_K_Acceptance_v0.3-MVP.md:193` — **AC-K-BR-Agreement-2** (LIVE: the closed-set + server-side clause + the negative path — *"attempt an out-of-set cadence (e.g. `weekly`, or a free-text typo such as `quaterly`), assert rejected at the API/DB layer"*).
- Module K PRD (canon) **§4.6 + §14.6** (BR-K-Agreement-2); bridges **DEC-042** + the tech-team GitHub question (ProducerAgreement settlement cadence).

**Frozen spec (what we carry — the open set):**
- `spec/04-decisions/decisions.md:291` — **DEC-042** (*Producer settlement: quarterly default, agreement-configurable*) — present; carries the **open** "e.g." framework MVP-DEC-010 closes (no launch value-set constraint).
- `spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md:186` — **AC-K-BR-Agreement-2** (the **open** version: *"per-Producer overrides are admitted via the agreement"* — no closed set, no server-side clause). `spec.lock` `source_sha: 4f48277…` (MVP-DEC-007).
- ⚠️ `spec/04-decisions/decisions.md:76` — greenfield **DEC-010** (*12.5% margin on club sales, 5% revenue share on Discovery*, partly amended `:239`) — **unrelated** number collision (see banner).

**Local code (the guard surface):**
- `database/migrations/2026_06_15_000004_create_parties_producer_agreements_table.php:59` — `settlement_cadence` = `->string()->nullable()` (free text, no CHECK today). The CHECK regen lands here (task 2.1).
- `app/Modules/Parties/Models/ProducerAgreement.php:36` — `@property string|null $settlement_cadence` (no cast today). The `SettlementCadence` cast lands here (task 2.1).
- `app/Modules/Parties/Actions/CreateProducerAgreement.php` — the server-side validation chokepoint (task 3.1).
- `database/seeders/DemoSeeder.php:296` — the out-of-set `'annual'` row (→ `semi-annual`, task 3.1). `database/factories/Parties/ProducerAgreementFactory.php:44` — default `'monthly'` (in-set, unchanged).
- New: `App\Modules\Parties\Enums\SettlementCadence` (task 2.1).
- Delta: `openspec/changes/parties-module-k-br-guards/specs/party-registry/spec.md` — *ProducerAgreement* (MODIFIED): the closed-set + server-enforced clause and the "Settlement cadence is a server-enforced closed set" scenario (`:312`).

**Related ADRs:**
- **Sibling mini-ADRs authored in this same change (RM-23 + RM-22):** [[2026-07-07-adopt-mvp-dec-009-agreement-club-active-scope]] (Agreement-4 — same ProducerAgreement entity, an *erratum of omission* vs this *value-domain tightening*) + `2026-07-07-adopt-mvp-dec-022-club-membership-governance` (Club-6 / Identity-6 / Profile-5 / Producer-5).
- **Same canon-adoption class** (each absent from the frozen spec, sourced from live `cmless/main`): [[2026-07-01-adopt-dec-008-hold-types-8]] · [[2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval]] · [[2026-07-02-adopt-dec-015-anonymisation-hold-block-set]] · [[2026-07-02-adopt-dec-019-review-freshness-resubmit]] · [[2026-07-02-adopt-dec-023-product-type-immutable]] · [[2026-07-02-adopt-dec-018-clubcredit-accrued]].
- [[2026-06-17-spec-synced-from-documentation-repo]] — why `spec/` is frozen + the read-only `git fetch cmless/main` grounding path.
