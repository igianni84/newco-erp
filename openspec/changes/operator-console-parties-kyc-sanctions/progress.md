# Progress — operator-console-parties-kyc-sanctions

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Surface-extension prep: relax the prior slice's absence-guard FIRST (keep every iteration green).** When this slice adds a verb a shipped test asserted absent (`assertActionDoesNotExist('verb')`), the group-1 prep task drops that ONE assertion *before* the verb lands — otherwise the moment the verb is wired the old guard turns red. Drop only the now-stale id; KEEP the guards for verbs still out of scope (here `submit`/`reject`/`reopen` — the Customer FSM is not review-governed). Also fix the stale prose (file header + test title) that named the verb "deliberately ABSENT" so the file isn't self-contradictory, and re-point the reader to where the verb is now pinned (`CustomerKycSanctionsConsoleTest`).

---

## [2026-06-22 17:34] — 1.1 Relax the now-stale absence guard
- Dropped the terminal `->assertActionDoesNotExist('requireKyc')` from the "exposes the four form-less status verbs" test in `CustomerLifecycleConsoleTest.php`; kept the `submit`/`reject`/`reopen` catalog-governance guards (`reopen` is now the terminal assertion). No account/profile absence guard existed in this file to touch (those stay deferred — never added).
- Fixed two now-stale references that named the KYC verb absent: the file-header scope note (lines 7–8) and the test title (`… without the catalog governance or KYC verbs` → `… without the catalog governance verbs`). Re-pointed both to `CustomerKycSanctionsConsoleTest`, where the KYC + sanctions verbs land this slice.
- Files changed: `tests/Feature/Modules/OperatorPanel/Parties/CustomerLifecycleConsoleTest.php` (test-only; no production code, no spec/arch files).
- Quality loop: **green** — Pint clean; `--filter=CustomerLifecycleConsoleTest` 9/9 (90 assn); full suite 1442/1442 (7948 assn, was 7950 — the single removed assertion); PHPStan max 0 errors; `pint --test` clean; `openspec validate --strict` green.
- **Learnings for future iterations:**
  - The KYC verbs are **visibility-gated** (design D4): on a fresh factory Customer (`kyc_status` null) `requireKyc` becomes VISIBLE the moment task 2.1 wires it — which is exactly why this absence guard had to go now to keep the loop green.
  - Next (task 1.2): pin the Filament 5 **page header-action** visibility API against installed 5.6.7 — `assertActionVisible/Hidden`, plus the mount-and-inspect path (`mountAction`, `assertFormFieldExists`, `setActionData`) on `Livewire::test(ViewCustomer::class, ['record' => $id])`. Do NOT write it from memory (arch-from-memory ban); record the confirmed helpers in the `ViewCustomer` docblock.
---
