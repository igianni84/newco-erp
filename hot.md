---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-10
---

# Hot Cache

## Last Updated
**2026-07-10 — F10 DECIDED (ADR landed); RM-26 + RM-27 opened; nothing in flight.** Tree clean, `openspec/changes/` holds only `archive/`. Doc-only session: two commits, zero `app/` diff.

## Build & Quality Status
- **SQLite 2389/2389** (12 404 assn) · **PG17 2389/2389** (12 411 assn) — PG surplus = the PG-only concurrency + CHECK lanes. Untouched today.
- PHPStan max **0** · Pint clean · `openspec validate --all --strict` **10/10**.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 lane: prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (container `pg`).

## Active Change & Next Task
- **None in flight.** Read `docs/validation/Remediation_Tracker.md` **§1 ▶️ NEXT** first — source of truth for what remains.
- **Next = the spec-refresh change**, per ADR [`2026-07-10-spec-vendoring-cadence-and-staleness-gate`](decisions/2026-07-10-spec-vendoring-cadence-and-staleness-gate.md): (a) build the **fail-closed staleness detector** + wire `SessionStart` + amend `/spec-to-change`; (b) run `sync-spec.sh` as a **code-free commit** (`spec/` + `spec.lock` only); (c) **triage pass** over the +502/−378 diff → an RM row per divergence. ⚠️ **Decided ≠ done** — no detector exists, no refresh has run.
- **Then RM-26** (+ RM-27), never before: canon calls `MVP-DEC-024` launch-blocking; after the refresh it is simply *in* `spec/`, so no mini-ADR.
- **Canon = `9eaa341` (MVP-DEC-036)**, **35 commits** past pin `4f48277`. It moved 6 DECs *during* the investigation.

## Blockers & Decisions Needed
- **None blocking.** Queued decisions, each with a *gate*:
  - **F12** — `Profile ↔ Customer` lock-order inversion can deadlock (`40P01`); pre-existing, no test sees it. Gate: before the producer-facing HTTP surface.
  - **Two canon escalations on capacity** — who evaluates the seat floor (`BR-A-Mutability-1` floors on *vouchers issued*, `AC-K-J-15` on *seats*); K PRD §1:77 (*S enforces*) vs §13 (*K*). Gate: before Module A's capacity-adjust.
  - **Outbound escalation channel** — 18 canon issues, all from the TS team, none ours. Named unresolved in the F10 ADR; **Giovanni's own ADR.**
- **Known divergences the refresh will surface:** `MVP-DEC-033` renames `SellableSKU*`→`IntrinsicSKU*` (we ship all 3; it rewrites `event_type` in the append-only 10-yr log — a migration question) · `034(a)` `XM-19` "NOT met" → **Defer** (and vindicates RM-05's zero-storage read-port) · `034(c)` **real gap**: the `AC-K-J-7a` compensating manual control is absent here · `035` reverses *"K never sends"*. `034(b)` verified **not ours**.
- **Also open:** F2 (🟥 go-live) · F5 · F6 · F7 · F9 (**OTP**, watch) · F11 (`DemoSeeder` not re-runnable on SQLite) · two §2.7 follow-ups.

## Open Patterns
- **A word repeated across artefacts becomes a constraint, and nothing reds.** F10 asked to supersede an ADR that had *authorised* the very thing it wanted — "frozen" was folklore, never a decision. Quote the decision; prose *about* it drifts.
- **A memory file that asserts a remote's state expires by design.** `hot.md` said *"11 commits ahead"* — true 13:12, false 13:15, falsified by the push it recorded as pending. **Ask the remote; never remember it.** Same root as the canon-staleness gap.
- **A vacuous gate is worse than no gate** — the first staleness detector compared an *empty string* to the pin and reported STALE; it would have said STALE while in sync. Fail closed. (3rd confirmation.)
- **A verifier's finding is a candidate, not a fact** (F1, twice). Full set in `lessons.md`.
