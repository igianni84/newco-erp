# Design — reconcile-hold-registry-eight-types

## Context

The `HoldType` domain went 6→8 (canon MVP-DEC-008, adopting `CHARGEBACK_REVIEW`/DEC-168 + `STORAGE_PAYMENT_FAILED`/DEC-160 as first-class values) in commit `d8ec261` (RM-04, 2026-07-01): enum, migration CHECK (derives from `HoldType::cases()`), operator-console place-form, and the whole test suite all moved to eight and are green. That commit was a Round-1 quick-win made **directly**, outside the `/spec-to-change → APPROVED → ralph → archive` flow — and the truth-spec is synced **only** at `openspec archive`. So the spec-of-record drifted: `openspec/specs/party-registry/spec.md` and `openspec/specs/operator-console/spec.md` still describe a six-value domain that contradicts the shipped eight-value code and the live canon. `Remediation_Tracker.md` §7 names this as **F4** ("RM-04 delta debt").

The truth-spec is "how the system behaves TODAY". A truth-spec that contradicts the shipped code is exactly the escalation-asymmetry drift the team-memory (`spec-divergence-from-cmless-documentation`) warns against — and the one an autonomous loop, reading the spec as ground truth, would trust. This change closes the loop: **the spec-of-record is made to agree with the code and the canon.** It is the *only* mechanism to do so (invariant 11 — `openspec/specs/**` changes only via an archived change).

## Goals / Non-Goals

**Goals**
- `openspec/specs/party-registry/spec.md` + `openspec/specs/operator-console/spec.md` state the eight-value `HoldType` domain, matching shipped code (`d8ec261`) and canon MVP-DEC-008.
- The two finance-driven types are documented as **operator-lift-only at launch**, with their Module-E auto-triggers/auto-lift as explicit deferred seams.
- Every modified/added scenario is backed by an existing green test (traceability), so the reconciliation is provably faithful, not aspirational.

**Non-Goals**
- **Any behaviour change, any production code, any new test, any migration.** The behaviour exists and is covered.
- Re-litigating DEC-008 (the ADR `2026-07-01-adopt-dec-008-hold-types-8` already decided it — this change adopts it).
- Wiring the Module-E consumers (`CustomerChargebackFlagged`, `StoragePaymentFailed`, `StoragePaymentSucceeded`) — deferred Phase-6 seams.
- Editing the Protected terminology files (`CONTEXT.md`, `CLAUDE.md`) — human hand-off (GUIDE §3).

## Decisions

- **D1 — Reconciliation via `## MODIFIED Requirements` deltas.** MODIFIED replaces a requirement wholesale by its **exact name** at archive; the delta reproduces the current requirement verbatim with only the eight-value tokens changed. Requirement names used verbatim: *Hold Registry*, *Hold Lifecycle and Lift Discipline*, *Hold-Driven Status Coupling* (party-registry); *Operator places and lifts Customer Holds through the console* (operator-console).
- **D2 — Include the source-note-only fix in *Hold-Driven Status Coupling*.** Its behaviour is count-independent (keys on Hold *coverage*, not the type set), so the only stale token is "six types" in a citation. Fixed for full truth-spec consistency (no lingering "six" anywhere), reproduced verbatim with a single-token change — the alternative (leaving it) keeps a self-contradiction in the spec-of-record.
- **D3 — Both finance-driven types are operator-lift-only at launch.** Per the adoption ADR: `chargeback_review` is resolved by an operator dispute review (no auto-lift signal); `storage_payment_failed` is manual-first (D4), and its `StoragePaymentSucceeded` per-cycle auto-lift is a deferred Module-E seam. So `autoLiftable()` stays exactly `kyc || payment` (unchanged), and the operator-liftable set grows 4 → 6. This matches `HoldType.php` (both new cases fall through to `false`) and `HoldEnumsTest`.
- **D4 — No new test; verify + trace.** The shipped suite already pins the eight-value contract at the exact assertion points the modified scenarios describe (`HoldEnumsTest` ordered-map + count + partition, and the five feature tests). A redundant test would be over-engineering. The ralph task is regression-run + a scenario→test traceability table in `progress.md`; a new assertion is added only if a modified scenario is found uncovered (none is expected).
- **D5 — Protected files flagged, not edited.** `CONTEXT.md` / `CLAUDE.md` are Protected; the change records the exact stale lines in `proposal.md` (Impact) and `progress.md` for Giovanni's hand-edit.

## Risks & landmines

- **L1 — Do NOT write code.** The eight types already exist in `HoldType.php`; a loop that "implements" them would duplicate/clobber shipped code. Every task is verify-only. If a task seems to require a code change, STOP — the premise is wrong.
- **L2 — Verbatim reproduction fidelity.** Each MODIFIED requirement reproduces the full current truth-spec text; **only** the enumerated tokens change (six→eight, the two type names, the operator-liftable list, the DEC-008/`AC-K-FSM-10`/`AC-K-FSM-11`/`AC-K-EVT-18/19` citations). Before validating, diff each MODIFIED block against the live `openspec/specs/**` and confirm the semantic diff is *exactly* the eight-value reconciliation — any other divergence is an accidental spec edit and must be reverted.
- **L3 — operator-console lift scenario under-enumeration.** The current console scenario lists only four Lift-exposing rows (`admin/fraud/compliance/credit`); the shipped console derives Lift from `NOT autoLiftable()` and shows six. The delta names all six — do not drop the two finance-driven rows, or the spec would under-describe the shipped console.
- **L4 — `storage_payment_failed` lift discipline is launch-scoped.** Canon `AC-K-FSM-11` gives it a per-cycle auto-lift on `StoragePaymentSucceeded`; we ship it operator-lift-only because that signal is an unbuilt Module-E path. The delta states the launch reality **and** names the deferred auto-lift seam — do not assert an unconditional never-auto-lift for it.
