# Module 0 & K Validation — "How we're positioned" (underwater build)

> **Context.** On 2026-07-01 Paolo Alfieri asked Taha (official tech team) for what's needed to **validate and close a module, Module 0 & K first**. This folder applies Paolo's exact three asks to **our** underwater AI-native build — to see where we stand vs the official effort, on the same yardstick.
> **Deliverables.** [`Module_0_Verdict_v0.3-MVP.md`](./Module_0_Verdict_v0.3-MVP.md) · [`Module_K_Verdict_v0.3-MVP.md`](./Module_K_Verdict_v0.3-MVP.md) — the annotated acceptance criteria (Verdict/Evidence/Notes) Paolo asked for.
> **Baseline & method.** Verdicts are against our **local frozen spec** (`spec/` = a c-mless `handoff/` snapshot at MVP-DEC-007); a **Canon Overlay** in each report layers on where the current c-mless canon (through DEC-023) has moved. Evidence = real code + the Pest suite (full suite 1753/1753 green; ~1423 tests touch these two modules), cited `file:line`. Reviewer-verified sample of every headline claim.
>
> ⚠️ **Amended 2026-07-10 — read the two paragraphs above as *dated*, not current.** (i) "**frozen**" was never a decision: [ADR 2026-07-10](../../decisions/2026-07-10-spec-vendoring-cadence-and-staleness-gate.md) establishes that `spec/` is the **build authority and chases canon** by deliberate refresh; the word here is one of the artefacts that turned an unexercised capability into folklore (`lessons.md` 2026-07-10). (ii) "canon **through DEC-023**" was already wrong when written and is now far off — canon was `360df0b` (MVP-DEC-030) on 2026-07-09 and **`9eaa341` (MVP-DEC-036)** on 2026-07-10. **These verdicts remain valid as written, against the baseline they name** (`spec.lock` → `4f48277`); they are a snapshot, not a live diff. Do not update them — check `spec.lock` and the staleness detector instead.

---

## Paolo's three asks → applied to us

| Paolo asked Taha for… | Our answer |
|---|---|
| **1. A verdict report** — acceptance doc + `Verdict / Evidence / Notes` per criterion; AUTO criteria auto-generated; downstream-dependent ones marked Deferred. | ✅ **Done** — the two module reports. Our AUTO evidence is the Pest suite; Deferred = the doc's own "verified when Module S/A/B/C/D/E lands" notes. |
| **2. A walkable test environment** — seeded UAT, **≥2 operator logins** (to prove self-approval blocked), data for **a Club at capacity, an anonymisation, a rejection round**. | ⚠️ **Not ready as asked** — see "Environment readiness" below. |
| **3. Confirmation of which acceptance-doc version we built against.** | ✅ **Answered — and we computed the delta for him.** We built against **MVP-DEC-007**; canon is at **DEC-023**. Paolo said *"if it's not the latest I'll send you the items that changed"* — we already mapped them (Canon Overlay). |

## The scoreboard (ask #1, vs local baseline)

| | Pass | Partial | Fail/Gap | Deferred | Total | Tests |
|---|---|---|---|---|---|---|
| **Module 0 (Catalog)** | 67 | 10 | 7 | 16 | 100 | 388 ✅ |
| **Module K (Parties)** | 77 | 26 | 19 | 8 | 130 | ~1035 ✅ |
| **Combined** | **144** | **36** | **26** | **24** | **230** | |

Excluding legitimately-Deferred items, **70% Pass** on in-scope criteria, with real, cited evidence. Two very different stories under the hood:

- **Module 0 (Catalog) — genuinely strong.** The PIM spine is mature and deeply tested: seven-entity FSM, **separation-of-duties enforced** (this is Paolo's self-approval check — Catalog passes it, through the Filament console too), the Producer activation gate, activation/retirement cascades, 21 of 22 events on the outbox substrate. Gaps are build-completeness holes: **bulk import** (5 criteria, zero code), the **Layer-1 case-config whitelist**, the `EnrichmentDataUpdated` event, re-versioning on edit.
- **Module K (Parties) — strong core, hollow edges.** The within-module spine + **compliance floor** (KYC + sanctions FSMs, unified Hold registry, DEC-181 read-API, Originating-Club lock, ProducerAgreement) is real and heavily tested. But everything cross-module is absent: **GDPR erasure** (floor), **Hero-Package capacity** (ships UNCAPPED), **enhanced-KYC threshold** (floor), segmentation, marketing, and **all Module-E/S event consumption** (`PartiesServiceProvider::boot()` is empty).

## The three headline canon divergences (ask #3)

The heaviest deltas all land on **Module K** — and precisely on the three scenarios Paolo named for the walkthrough. Not a coincidence: he's aiming the spotlight at the recent decisions.

1. **Membership flow (DEC-016).** Canon: producer approval `= charge = activation`, **atomic**, no "approved-but-unpaid" state; `MembershipFeePaid` from Module S; INV1, no INV0. **We built the flow canon declared *wrong*** — a distinct `Approved` state, activated separately by a deferred Module-E signal. → *Paolo's implicit "membership" case.*
2. **Capacity seat-set (DEC-011/017).** Canon: seat-set = `Active` + `Suspended`, enforced at the atomic approve-moment. **We don't enforce capacity at all** (UNCAPPED, deferred to Module A). → *Paolo's "Club hitting capacity" scenario.*
3. **Hold enum 6→8 (DEC-008) + erasure block-set (DEC-015).** Canon: 8 Hold types; only `compliance` blocks anonymisation. **Our enum is 6, and anonymisation doesn't exist.** → *Paolo's "anonymisation" scenario.*

Full mapping (16 new DECs, 8 new + ~35 touched criteria) in each report's Canon Overlay + `scratchpad/delta-ask3.md`.

## Environment readiness (ask #2) — **not ready as asked**

| Item | Verdict | Why |
|---|---|---|
| ≥2 operator logins | ⚠️ Partial | Exactly **one** operator seeded; **no supported way to add a second** (no artisan command, no Filament user resource). SoD keys on distinct actor → the single operator can't approve its own entities. A ~1-line seeder change fixes this. |
| Self-approval blocked | ✅ Ready (Catalog) / ❌ Gap (Parties) | Catalog SoD is real + console-tested; **Parties membership approval has no distinct-actor guard**. |
| Scenario: Club at capacity | ❌ Gap | Capacity is an **unbuilt feature** (Module A is an empty stub), not a seed-data gap. |
| Scenario: anonymisation | ❌ Gap | Anonymisation is an **unbuilt feature** (floor). Note: ADR `2026-06-15-identity-auth.md:23,84` describes erasure as "already built" — only the *audit-redaction* substrate is; the customer PII-erasure flow (J-9/9a) is not. **Reconcile the ADR.** |
| Scenario: rejection round | ⚠️ Partial | `submit → reject → distinct-actor activate` is walkable + tested; **`edit-in-place → re-submit` doesn't exist** (no Edit pages / Update actions) — and canon DEC-019 raises the bar further. |

## What this means (the underwater lens)

- **The hard core is real.** In a short window the AI-native build produced a **deeply-tested** implementation of the two hardest foundations — the PIM spine and the compliance floor — with ~1,400 passing tests, honest verdicts, and enforced separation-of-duties. That's the thing worth showing Paolo.
- **The gaps are honestly scoped and mostly *known-deferred*** (capacity, cross-module consumption legitimately wait on Modules A/S/E, which don't exist yet). The genuine worries are the **compliance-floor** items that should **not** wait: **GDPR erasure** and **enhanced-KYC threshold**.
- **We did ask #3 for Paolo, and it flows the other way too.** Reconciling our freeze against canon surfaced that **our own frozen handoff carried the wrong membership flow** (later corrected by DEC-016) — a finding that belongs back in the shared record. This exercise is the "spec-corrections inbox" that closes our known escalation-asymmetry gap (memory: `spec-divergence-from-cmless-documentation`).

## Recommended next steps (if we go further)

1. **Close the walkable-env gap cheaply:** seed 2–3 distinct operators + chain `DemoSeeder` — makes the *rejection-round* + SoD scenarios demoable now.
2. **Reconcile the ADR overclaim** on GDPR erasure (identity-auth ADR vs actual code).
3. **Decide the canon-catch-up posture:** write local ADRs for DEC-008..023 (the ones touching 0/K), starting with the membership-flow correction (DEC-016) — it's a design change, not a tweak.
4. **Post the genuine gaps upstream** as c-mless issues where we found real spec ambiguity (closing the loop both ways).
5. **Prioritise the two floor gaps** (GDPR erasure, enhanced-KYC) over the Deferred cross-module items — they're compliance, not convenience.
