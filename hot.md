---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-08
---

# Hot Cache

## Last Updated
**2026-07-08 (late) — `catalog-module-0-completeness-sweep` MERGED, semantic-verified, ARCHIVED, PUSHED.** Full §2.7 ritual: review → PG17 verify → `--no-ff` merge → 3-pass semantic verify → remediation (tasks 8.1–8.4) → `openspec archive` → `git push` + branch deleted. `main` == `origin/main`; `openspec list` = no active changes. Clean slate.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Both engines:** SQLite **2221/2221** (11 738 assertions) · PG17 **2221/2221** (11 741 — surplus is the PG-only CHECK lane). PHPStan **0** · Pint clean · `validate --all --strict` 10/10. Truth specs: `product-catalog` 21 · `operator-console` 31.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). **PG17:** prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (container `pg` is up).

## Active Change & Next Task
- **None in flight.** Archive: `openspec/changes/archive/2026-07-08-catalog-module-0-completeness-sweep/`. Its `progress.md` carries the delta→test table, the 5 deferred seams, the §2.7 verify section, **7 latent follow-ups** (DemoSeeder watermark trap; `isReviewStale()` missing `module` predicate; `catch (RuntimeException)` swallowing `QueryException`; `is_breakable` asserted on 2 of 11 catalog tables; no `lang/it/catalog.php`; 6 spine console re-submit tests understated; `producer_name` never projected) and `## Codebase Patterns` (132 bullets). **Read it before touching Module 0.**
- **NEXT: RM-05** (capacity seat-set + WaitingList, the last P1) via the **K-side seam, ADR-first** — its own session.
- Landmines: console keys EN+IT (catalog **domain** reasons EN-only); a `{@see FQCN}` on a `Catalog\Events`/`Lifecycle` type — Pint auto-imports it, redding `ModuleBoundariesTest`.

## Blockers & Decisions Needed
- None. A stray local branch `parties-anonymisation` predates this session — untouched.

## Open Patterns
- **`==` / `!=` on two arrays is NOT structural equality** (`lessons.md` 2026-07-08). It recurses into loose VALUE comparison: on PHP 8 two *numeric strings* compare numerically (`'1e2' == '100'`). A docblock justifying a loose operator by what it permits ("ignores key order") must also say what else it admits. A comparison that decides whether a domain event fires is domain logic — name it on the value object (`TranslatableText::sameContent()`).
- **A discipline a green suite can violate must be a guard, not a comment.** D5's verb-collision rule lived in prose; `CatalogContentEdit::maintain()` now enforces it against the one `REVIEW_FRESHNESS_VERBS` list.
- **A verifier's finding is a CANDIDATE, not a fact.** 1 of 5 WARNINGs this pass rested on a fabricated premise ("six Parties view pages extend `OperatorConsoleViewRecord`" — none do). A subagent's confidence is not evidence.
- **A scenario's coverage is an ORDERING claim, not a set-of-facts claim** (`knowledge/testing/hypotheses.md`); siblings diverge silently in test SHAPE. And an "untouched" ids-snapshot passes for free on an EMPTY trail — pin the literal ordered ACTION list.
- **A residual-claim sweep must include `tests/` and re-check its own list** — 7.1 swept `app/` and still missed two docblocks it had itself enumerated.
- **Three Filament test helpers lie** (`knowledge/filament/hypotheses.md`): `callAction()` cannot SHRINK a prefilled list; `assertHasActionErrors()` truncates at the first `:`; `assertNotified()` asserts the TITLE.
- **`ApprovalGovernance::creatorOf` reads the EARLIEST `domain_events` row, unfiltered** — an SoD-subject test must build through the real `Create*` lineage. **The grep is the candidate set; only the FULL suite is the blast radius.**
