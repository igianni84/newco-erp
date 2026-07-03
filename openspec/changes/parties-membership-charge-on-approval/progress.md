# Progress — parties-membership-charge-on-approval

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Prove a docblock/seam-name change is behaviour-neutral** with `git diff <file> | grep -E '^[+-]' | grep -vE '^[+-]{3}' | grep -vE '^\s*[+-]\s*\*'` → empty output means every changed line is a `*` comment. Pair it with the Action's isolated contract test passing **unedited** + PHPStan resolving the docblock `{@see}` refs.
- **Docblock `{@see}` imports:** same-namespace Actions (`ApproveProfile`, `DeclineProfile`, `ActivateProducer`, `CloseClub`, `IssueClubCredit`) and already-imported classes need **no** new `use` — only a *cross-namespace* `{@see}` would force one (Pint import-boundary lesson 2026-06-20). Reference `MembershipFeePaid` as backticked prose, never `{@see}` (no class exists — zero-invention).
- **Docs-first within a change:** a docblock may describe the change's **target** design (here: `ApproveProfile` invoking `ActivateProfile` synchronously) one task before the wiring lands, as long as no test asserts the wiring from prose — green stays green between tasks.

---

## [2026-07-03 10:41] — 1.1 `ActivateProfile` — re-home the `MembershipFeePaid` seam E → S (docblock-only, behaviour-neutral)
- **What:** Rewrote the `ActivateProfile` class docblock to (a) **re-home the `MembershipFeePaid` seam E → S** — *Module S emits; Module E records; Module K consumes* (DEC-173), firing **INV1, no INV0** (DEC-157) — correcting the frozen "Module E / INV0" framing; (b) state the **TWO INVOCATION MODES** — synchronously by `ApproveProfile` inside the approval transaction (the K-internal atomic activate-on-approval) now, and by the Module-S `MembershipFeePaid` listener later; (c) reframe the first paragraph so the approve/decline **writes** are audit-only while `ApproveProfile` drives straight through activation (canon MVP-DEC-016, `Approved` transient). Executable code (`handle()`, ctor, imports) untouched; no `MembershipFeePaid` class fabricated.
- **Files changed:** `app/Modules/Parties/Actions/ActivateProfile.php` (docblock only, +25 / −13 comment lines); `openspec/changes/parties-membership-charge-on-approval/tasks.md` (1.1 checked).
- **Quality loop: green** — Pint format + `--test` clean; `--filter=ProfileActivationTest` 5/5 (test file unedited); full suite `php -d memory_limit=2G vendor/bin/pest` **1947/1947** (10459 assertions, SQLite); PHPStan max **0 errors**; `openspec validate parties-membership-charge-on-approval --strict` valid.
- **Acceptance:** all 4 bullets met — docblock cites DEC-173 + DEC-157 + the two modes; no behaviour change (every diff line is a `*` comment; suite green); `ProfileActivationTest` unchanged (incl. the `cannotActivate` from-state cases); no `MembershipFeePaid` event file under `app/Modules/Parties/Events/`.
- **Learnings for future iterations:**
  - This was the behaviour-neutral prep for **1.2**. The docblock now *describes* `ApproveProfile` invoking `ActivateProfile` synchronously ("mode (a)"), but the real wiring lands in **1.2** — the docblock states the change's target design (docs-first), and green holds because no test asserts wiring from prose.
  - `{@see ApproveProfile}` anticipates 1.2 yet resolves today (same namespace, class exists). Kept the `ActivateProducer` KYC-seam analogy; corrected the Club-Credit note's "Module E's listener" → "Module S's listener" (DEC-173 consistency).
  - Cross-engine PG **not** run here (docblock-only, no runtime surface) — that is task **3.1**'s explicit job.

---
