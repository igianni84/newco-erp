# NewCo ERP — Launch-MVP Dials Worksheet v0.1

- **Version**: v0.1 (DRAFT — workshop worksheet; recommendations pending Paolo's decisions)
- **Date**: 2026-06-05
- **Status**: OPEN — Phase A dials workshop in progress
- **Owner**: Paolo
- **Inputs**: [`MVP_Restructure_Plan_v0.1.md`](MVP_Restructure_Plan_v0.1.md) (method), [`Dials_Grounding_v1.1_reference.md`](Dials_Grounding_v1.1_reference.md) (evidence).
- **Cut depth**: **Lean MVP** (locked 2026-06-05). Default = defer / manual-first; KEEP only what the core loop or the compliance/data-integrity floor requires.

> **"Saves"** is a rough *engineering-weight* signal (S/M/L/XL), not a calendar promise — the calendar number comes from the dev-team sizing exercise (Phase E). **"Paolo call"** marks dials where commercial/brand/contract judgment should override the engineering default.

## §1 Recommendation table

| # | Dial | Lean recommendation | Why | Saves | Paolo call? |
|---|------|--------------------|-----|:---:|:---:|
| D1 | Currencies (5) | **KEEP capability, NARROW set** | Dual-record machinery cost is ~fixed regardless of count; narrowing trims config/QA only. | S | ✅ market |
| D2 | Locales (6/2/2) | **SIMPLIFY → EN + top markets at launch** | i18n×6 is a Phase-7 cost; adding locales later is config, not migration. | M | ✅ market |
| D3 | Geography incl. US | **DEFER US + high-friction; launch EU/UK/CH** | US OFAC + state-by-state alcohol = potentially the single biggest compliance workstream. | XL | ✅ market |
| D4 | Payments (card/SEPA/saved-card) | **KEEP card+SEPA; DEFER saved-card auto-escalation** | Card+SEPA are core loop; INV3 escalation is tied to storage billing months out → manual first cycle. | M | — |
| D5 | Gifting | **DEFER** | Not in the core loop; clean post-launch add. | M | ✅ differentiator? |
| D6 | Cancellation/refund | **KEEP 14-day (legal) + basic refund; SIMPLIFY the matrix** | Keep the legal floor; defer goodwill/partial-voucher sophistication to manual handling. | M | — |
| D7 | Discovery multi-producer composites | **DEFER (single-producer offers at launch)** | Atomic multi-allocation bind is heavy S/A; can Discovery launch single-producer? | L | ✅ business pillar |
| D8 | Club / membership model | **KEEP core; SIMPLIFY mechanics; consider single-profile** | The business — keep; but stacking/Club-Credit/multi-profile are heavy. | L | ✅ business model |
| D9 | Catalog LWIN + bulk import | **KEEP (already in Mod 0 build); SIMPLIFY bulk** | Low incremental cost, big ops value; basic CSV import to seed the launch catalog. | S | — |
| D10 | Allocation sub-pools | **KEEP** | Core supply-side primitive; already trimmed (BOTH-value dropped). | — | — |
| D11 | Procurement variants (3) | **SIMPLIFY → launch the variant(s) actual producers use** | Three variants → only the ones the launch producer deals require. | M | ✅ deal-dependent |
| D12 | NFT / blockchain + NFC | **DEFER on-chain; KEEP physical NFC + off-chain provenance** | Removes Avalanche + the external expert-review gate from the critical path. | XL | ✅ brand |
| D13 | Late-binding pick algorithm | **SIMPLIFY → FIFO + manual tiebreak** | Warehouse-efficiency optimization is unneeded at launch volume. | L | — |
| D14 | Returns / replacements | **SIMPLIFY → manual-first (Admin Panel)** | Low launch volume; operators handle; defer the FSM automation. | L | — |
| D15 | Producer recall | **KEEP minimal / manual** | Already lean (manual recording); rare event. | — | — |
| D16 | Stocktake / quarantine / adjustments | **KEEP integrity core; SIMPLIFY the workflows** | No-overselling + stock position are non-negotiable; stocktake/quarantine/adjustment *sophistication* → manual. **Delicate — joint review.** | L | — |
| D17 | Cellar render | **SIMPLIFY → basic view** | Defer richest aggregation (in-transit ETA, granular storage). | M | — |
| D18 | Multi-currency dual-recording | **KEEP (if D1 multi-currency)** | Data-integrity heart of FX-correct refunds; unsafe to simplify. | — | tied to D1 |
| D19 | Supplier settlement (quarterly) | **DEFER → operator-run first cycle(s)** | First quarterly close is months post-launch; build automation after. | L | — |
| D20 | Italian SDI connector | *(already deferred — EXT-2)* | No action. | — | — |
| D21 | Chargeback ingestion | **SIMPLIFY → manual at launch** | Operator handles via Airwallex + manual Hold; defer auto-ingestion. | M | — |
| D22 | Services / experiences | **KEEP storage-only** *(already lean)* | No action. | — | — |
| D23 | Producer reporting depth | **SIMPLIFY → minimal / operator-produced** | Few producers at launch; hand-produce; defer the uniform portal reporting. | M | ✅ contract? |
| D24 | Admin Panel | **KEEP (scoped to MVP ops)** | *More* important in a manual-first MVP — operators run what we deferred automating. | — | — |
| D25 | Support tooling | **KEEP email + admin lookup** *(already lean)* | No action. | — | — |

## §2 Where the time comes from (the big levers)

In rough descending order of engineering weight removed from the **launch critical path**:

1. **D12 Defer on-chain NFT/blockchain** — removes Avalanche integration, custodial wallet, mint/burn lifecycle, 4 recovery scenarios *and* the external blockchain-expert-review gate (which may not even close in time).
2. **D3 Defer US + high-friction geography** — removes OFAC + US-state alcohol compliance, the heaviest regulatory workstream.
3. **D19 Defer settlement automation** — the first quarterly close lands months after launch, so the whole 5-section settlement engine can be operator-run first and automated later.
4. **D13 + D14 Simplify pick + manual returns** — strips two of the heaviest mechanisms out of Module C (the highest cross-module-density module).
5. **D16 Simplify inventory workflows** (keeping the integrity core) — trims the Stage-8 net-new layer, the single largest increment in v1.1. *Delicate; we review jointly.*
6. **D8 + D7 Simplify club + defer Discovery composites** — trims the heaviest commerce/eligibility machinery. *Business-model calls.*
7. **D21 Manual chargebacks · D6 simpler refunds · D2 fewer locales · D5 defer gifting** — a long tail of medium cuts.

## §3 Decision posture

- **Engineering-default dials** (no ✅): proceed on the Lean recommendation unless Paolo flags otherwise during per-module ratification.
- **Paolo-call dials** (✅): commercial/brand/contract judgment leads. Worked in the dials workshop. Most consequential: **D3 (US?), D12 (NFT?), D8 (club richness?), D7 (Discovery?)**.
- **Already-deferred** (D20, parts of D22/D23/D25): no action; carried into the post-launch roadmap as-is.

## §4 Open dependencies on these decisions
- D1 ↔ D18 (currency set drives dual-record scope).
- D7 ↔ D8 (Discovery composites depend on the club/Discovery surface model).
- D3 ↔ D2 ↔ D1 (market footprint: which countries → which locales/currencies).
- D12 brand decision interacts with the Module B Bottle Page surface (D16/D17).

## §5 Decisions log (locked with Paolo 2026-06-05)

**Two binding principles now govern every cut** (see plan §4.1): **P1 — defer without burning bridges** (every cut names a forward-compat seam); **P2 — admin-first, self-serve-later** (operators drive producer/back-office features via Admin Panel; defer self-serve UI; consumer storefront exempt).

**Macro dials — decided:**

| Dial | Decision | Notes / forward-compat seam |
|---|---|---|
| D1 Currencies | **KEEP all 5** | Machinery is fixed-cost; cutting saves nothing. |
| D2 Locales | **KEEP** | i18n infra reportedly already built — *✱confirm with team*; only translation content remains (may stagger). No structural saving. |
| D3 Geography | **SIMPLIFY (hybrid)** | Automate low-friction destinations; complex ones (US, high-excise, state-alcohol) via manual operator flow: request → operator quote (duties/customs) → customer pays quote → ship. OFAC screening retained (legal floor). Automated US-state/excise engines stay deferred (DEC-148/149/150). *Seam:* manual flow records the same shipment/payment/excise data a future automated engine consumes. |
| D5 Gifting | **DEFER** | *Seam:* preserve voucher ownership-transfer capability (no hard single-permanent-owner assumption). |
| D7 Discovery composites | **SIMPLIFY → single-producer at launch** | Defer multi-producer atomic composites. *Seam:* keep Offer/Allocation able to reference N constituents later (Composite SKU concept retained in Mod 0). |
| D8 Club model | **KEEP (full — core VP)** | No upfront cut; detailed savings-hunt during Module K/S triage. |
| D11 Procurement | **KEEP both consignment variants; DEFER Direct Purchase** | *Seam:* uniform procurement flow (DEC-093) stays parameterized so Direct Purchase slots in later. |
| D12 NFT/blockchain | **KEEP (core VP), DECOUPLED parallel workstream** | Doesn't serialize the main ERP path. *Critical dependency:* blockchain-expert review must be expedited/scheduled now or it becomes the critical path (Paolo track). |
| D23 Producer reporting | **KEEP full self-serve** | Reporting is core to the producer promise; all 7 sections self-serve + real-time at launch. Data already computed by the §8 cross-module reads. |

**Cross-cutting lever:**

- **L-PP — Producer portal = full read, ops-driven writes.** At launch the producer portal is **view-only across almost everything (including the full 7-section reporting), with ONE producer write retained: membership approve/decline.** All other producer writes (allocation publication, offer publication, Hero Package designation, procurement intent) are operator-driven via the Admin Panel (admin-parity DEC-083). *Seam (P1):* producer-facing write UIs built post-launch on the same backend. (Instance of P2.)

**Engineering-default dials** (proceed on §1 Lean rec unless flagged at per-module ratification): D4 defer saved-card auto-escalation · D6 keep legal 14-day + simplify refund matrix · D9 keep LWIN + basic bulk · D10 keep allocation · D13 simplify pick→FIFO · D14 manual returns · D15 manual recall · D16 keep integrity core + simplify inventory workflows (delicate — joint review) · D17 simplify cellar · D18 keep dual-record (tied to D1) · D19 defer settlement automation→operator-run · D21 manual chargebacks · D22 storage-only · D24 keep Admin Panel (scoped, now load-bearing per P2) · D25 email+admin support.

**Open inputs to confirm with the tech team:** (1) i18n infrastructure completeness (D2); (2) the current estimate decomposed by module/phase; (3) blockchain-expert-review scheduling (D12 critical-path risk).

**Schedule honesty:** keeping NFT (decoupled) + the full club model + full self-serve producer reporting means scope-cuts alone are thinner than maximum-Lean — these are deliberate value-prop protections. The producer-side saving narrows to deferring producer *write* UIs (ops-driven via Admin Panel). The launch timeline now rests on three legs — decoupled/parallel NFT + the club savings-hunt; the engineering-default cuts landing (settlement-defer, pick-simplify, manual returns, inventory-workflow trim, manual chargebacks, geography-manual…); and team capacity — with the dev-team sizing exercise (plan Phase E) as the truth-test.

---

*End of Dials Worksheet v0.1 — Paolo decisions logged §5 2026-06-05; D23 producer-reporting deep-dive in progress.*
