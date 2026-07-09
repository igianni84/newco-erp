---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-10
---

# Hot Cache

## Last Updated
**2026-07-10 — `parties-hero-package-residuals` 4.1 green. CHANGE COMPLETE (6/6).** The close gate. Awaiting human review · merge · archive · push.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **SQLite 2389/2389** (12 404 assn) · **PG17 2389/2389** (12 411 assn) — identical to 3.2's baseline, as a doc-only task must be.
- PHPStan **0** · Pint clean · `openspec validate --all --strict` **11/11**.
- **`git diff --stat main...HEAD`: 11 files, ZERO under `app/`** — design R1 held across all six tasks. The code was always right; the spec and the suite were wrong.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 lane: prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (container `pg`).

## Active Change & Next Task
- **`parties-hero-package-residuals` — all 6 tasks done.** Branch `ralph/parties-hero-package-residuals`. Nothing left for the loop.
- **Human next:** review → merge `--no-ff` → `openspec archive` → push. **Only the archive** folds the corrected sequence (*guard → lock → count → read → gate*) into `openspec/specs/party-registry/spec.md:670`, which still carries the superseded ordering — expected, invariant 11.
- Shipped: corrected prose + 4 mutation-verified pins (guard-before-lock, pinned **negatively** · `WaitingListJoined` root-ness at **both** `record()` sites · console create/renew-at-capacity).

## Blockers & Decisions Needed
- **None blocking.** Two canon escalations stay OPEN, due before Module A's capacity-adjust: the capacity-decrease seat floor (`AC-K-J-15` floors on **seats**, `BR-A-Mutability-1` on **vouchers issued**); K PRD §1:77 (*S enforces*) vs §13 (*K*).
- **RM-05 closes against a documented SUBSET.** `AC-K-J-14` · `J-15` · `J-15a` · `XM-19` untouched by this change too. *"Residuals closed" ≠ "capacity is compliant"* — now in the tracker's §4.
- **`.env.example` is a test-environment file.** `PARTIES_HERO_PACKAGE_CAPACITY` ships **commented out**, pinned by a test — an active value caps the suite.
- **Tracker §7:** **F12** `Profile↔Customer` lock-order inversion can deadlock — pre-existing, needs a *decision* before the producer HTTP surface. Also F11 · F2 · F5–F7 · F8 (→ RM-26/27) · F9 (**OTP**) · F10 (ADR).
- One suggestion parked in `progress.md`: `party-registry:889` should state `RenewProfile`'s guard-before-lock order normatively.

## Open Patterns
- **A close-gate sweep is a CLASSIFICATION, not a count** — three of four hit-classes are *supposed* to fire (truth spec: folds at archive · archived history: immutable · the change's own quotations: deliberate). Only a **live** doc/docblock is a defect. Measured: 11 hits, 0 defects. Two greps whose *intersection* is the defect beat one whose union is noise.
- **A status marker duplicated across sections goes stale in every copy but the one you edit.** The tracker said semantic-verify "has NOT run" while F12 was stamped *"found by RM-05 §2.7 semantic-verify"*. Correct current-state sections; never rewrite append-only chronologies.
- **A doc-only task must move the assertion count by ZERO.** "Green" without a baseline verifies nothing.
- **A prescribed mutant is usually the loud one** — it proves the pin fires; necessity needs the quiet drift the suite passes. Find its red by test NAME + MESSAGE, not the reported line.
- **A sentence that orders operations is a claim, and it can ship false** — in a spec, a test's name, or a tracker's status line.
