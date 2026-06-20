---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-19
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-19 (interactive — STEP-1 ADR + first operator-UI change authored).** Kicked off the **Operator Admin UI** (Filament) over the shipped Modules 0 + K. STEP 1: grill-with-docs resolved the architectural gate → ADR `2026-06-19-operator-console-read-binding-write-through-actions` (+ INDEX + CONTEXT term **Operator console**): the **OperatorPanel is the composition layer** — Filament resources read-bind to module Eloquent models **read-only**, every write routes through the module's **domain actions** (never `$model->save()`), enforced by a CI architecture test. STEP 2: authored change **`operator-console-catalog-master`** via spec-to-change (`validate --strict` green). **No code yet** — authoring only; awaiting human APPROVED → ralph.

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **949/949 green** (last verified at the `parties-membership-suspension` close; this session added **no code**). `composer.json/lock` untouched.
- This ADR is the repo's first **frontend/UI** decision; the repo stays backend-only until `operator-console-catalog-master` is implemented.

## Active Change & Next Task
- **Active change: `operator-console-catalog-master`** (authored, NOT yet APPROVED). Founds the `operator-console` capability (discovery repoint to `app/Modules/OperatorPanel/Filament/**`; the no-Eloquent-write arch test; import-boundary carve-out for OperatorPanel reads; `actor_role:newco_ops` envelope; the SoD "second-actor" affordance) + the **ProductMaster** console end-to-end (create/submit/reject/activate/retire/cascade/reopen). 7 reqs / 15 scenarios / **11 tasks**.
- **Next:** human reviews → `touch openspec/changes/operator-console-catalog-master/APPROVED` → `./ralph.sh --change operator-console-catalog-master <n>`. Then **`operator-console-catalog-spine`** (other 6 spine entities), then **change 2 = the Parties console**.
- **OUT (no backend):** bulk import, enrichment-metadata update, field-editing a Master — flagged deferred-pending-backend, not silently cut.

## Blockers & Decisions Needed
- **Awaiting human APPROVED** on `operator-console-catalog-master` (the APPROVED marker is human-only). No other blocker; `main` in sync with `origin/main`.

## Open Patterns
- **Operator-console pattern (new, this ADR):** resources read-bind module models; every mutation `app(<Action>)->handle()`; default mutating paths (implicit save / `DeleteAction`) disabled; SoD (`ApprovalGovernance`) + Producer gate (`ProducerActivationGate`) are **surfaced, never reimplemented**; a CI arch test bans Eloquent writes in `App\Modules\OperatorPanel`. Proven on ProductMaster here; reused by the spine + Parties consoles.
- Filament writes flow through domain actions via `handleRecordCreation` (create) + Filament Actions (lifecycle) — verify Filament 5 signatures in `vendor/` before wiring (lessons.md).
