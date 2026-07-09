---
type: meta
description: Hot cache — repo-state digest, overwritten each operation. Chronology lives in log.md.
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — RM-05 task 7.1 green: the docs now say what the code does.** Six false claims in `CONTEXT.md` inverted, `WaitingListJoined` given its payload row, `Club seat` added to the glossary, three stale comments fixed with their pins intact. **Zero executable lines changed.** `ralph/parties-hero-package`, **15/16**, unpushed.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **SQLite 2381/2381** (12340 assertions). PHPStan **0** · Pint clean · `validate --strict` green.
- PG17 not re-run for 7.1 — the `.php` diff is provably comment-only. **7.2 owns both engines.**
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 prefix: `tasks.md` header.

## Active Change & Next Task
- **`parties-hero-package` — 15/16.** Next and last: **7.2 — the close gate.**
- **7.2 is verification + honesty, not code.** Four things, in order:
  1. Full suite **SQLite** *and* **PG17** (the PG17 lane is the ONLY place 3.2's two-connection concurrency proof runs — container `pg` must be up). Then `phpstan` ⇒ 0, `pint --test`, `openspec validate --all --strict`.
  2. ⚠️ **`docs/validation/Remediation_Tracker.md` §3/§4: RM-05 closes against a documented SUBSET.** `AC-K-J-14` / `J-15` / `J-15a` / `XM-19` are **NOT met** (Module A's capacity-adjust surface; the unmodelled period rollover; Module 0/S). **If the tracker doesn't say this, a later reader believes capacity is fully compliant — the single biggest honesty risk of this change.**
  3. Record the two incidental **RM-03 residuals** corrected in `openspec/specs/operator-console/spec.md`, and the two canon escalations that stay open (who evaluates the capacity-decrease seat floor; K PRD §1:77 vs §13 on the enforcer).
  4. `log.md` via `scripts/memlog.sh`; `hot.md` overwritten.
- **Read `progress.md` § Codebase Patterns first** — every landmine of this change.

## Blockers & Decisions Needed
- **None blocking.** Three things 7.2 should know:
  1. **`openspec/specs/party-registry/spec.md:931` is NOT stale — do not "fix" it.** It carries `CONTEXT.md:287`'s old sentence, but **scoped** (*"by this change"*), which is why `MembershipSuspensionChainTest`'s pin stays green and why the delta rightly does not MODIFY *Demand-Side Status Events*. Never hand-edit `openspec/specs/**`. Worth **one** fresh-eyes read at semantic-verify: a reader who drops the qualifier re-creates the defect 7.1 just removed.
  2. **`.env.example` is a test-environment file.** `APP_ENV=testing` loads `.env`; `phpunit.xml` overrides only ~13 keys; `docs/development.md:23` says `cp .env.example .env`. `PARTIES_HERO_PACKAGE_CAPACITY` ships **commented out**, pinned by a test. An active value there caps the whole suite.
  3. **Only the full suite is proof.** 7 of the 16 files exercising these Actions drive them through `callAction`, invisible to `grep`.
- **Tracker §7:** F1 (DemoSeeder PG-truncate) did **not** bite. F8 (MVP-DEC-030 → RM-26/27) · F9 (canon #18 OPEN: auth is **OTP**) · F10 (`spec/` 29 commits behind — needs an ADR).

## Open Patterns
- **A residual-claim sweep needs two greps.** The ambient vocabulary (`uncapped`) finds candidates but then drowns in your own new true prose; the **stale phrasings as verbatim needles** (`ships uncapped`, `is a deferred Module-A seam`) must return zero. The second grep is the proof — it caught `CONTEXT.md:174` after the first list was already walked.
- **A comment that explains a pin can rot while the pin stays true**, and nothing reds. That is the dangerous case: the next reader deletes the pin, or "fixes" the code to match the comment.
- **A green acceptance test can pass against a racy implementation**, an omission-test against the omission's opposite, a capped-behaviour test against a fixture that could never have qualified. Mutation is the only proof.
