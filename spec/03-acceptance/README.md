# `03-acceptance/` — the acceptance criteria

One acceptance doc **per module PRD** — its testable companion, re-cut to the launch (v0.3-MVP) scope. Read each alongside its PRD in [`../02-prd/`](../02-prd/); build the module to its PRD, verify it against this doc.

Each doc is a table of criteria (mostly `AUTO` — automatable test scenarios — plus some manual checks), keyed to the PRD sections and the business rules they verify. Deferred-feature criteria are annotated as out-of-launch-scope; the **floor criteria** (no-overselling, KYC/sanctions/Hold, tax-correct invoicing, dual-record FX, committed-inventory, audit/retention) are unchanged and are the ones that prove the launch floor holds.

## Resolving the anchors in the criteria

Some criteria cite their authority by reference:
- **`BMD §X.Y`** → [`../00-business-model/NewCo_BusinessModel_v0.9.md`](../00-business-model/NewCo_BusinessModel_v0.9.md) (the pinned business-model version) — backs business facts such as the storage rate, the OC share, the invoice typology, the FX-refund rule.
- **`DEC-###`** → [`../04-decisions/decisions.md`](../04-decisions/decisions.md) (the frozen DEC-001..196 register).

The paired PRD section usually carries the same substance inline, so the anchor is a cross-check, not a prerequisite — but both files are in this package so every anchor resolves.
