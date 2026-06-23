# Progress — club-credit

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Parties state-enum idiom (sibling templates `KycStatus`/`HoldType`):** a `string`-backed `enum` in `app/Modules/Parties/Enums/`, **no `declare(strict_types=1)`** (no sibling has it), case names PascalCase, backing values the lowercase persisted token. A class-level docblock cites the PRD § + the verbatim FSM. Domain rules live as **one-line predicate methods** (`clears()`, `autoLiftable()`, here `isActive()`/`isTerminal()`) each with its own docblock — these are the readable from-state guards the writer Actions call. Test in a domain-grouped `tests/Unit/Modules/Parties/Enums/{Domain}EnumsTest.php` (Pest `it()` style) asserting the case→value map **verbatim + order-sensitive via `toBe([...])`** + `toHaveCount(n)`, a `from()` round-trip, the predicate truth tables, and `from('bogus')->toThrow(ValueError::class)`.
- **Full-suite invocation — use `php -d memory_limit=-1 vendor/bin/pest`, NOT `php -d memory_limit=-1 artisan test`.** The `artisan test` wrapper (laravel/pao) spawns the real test process *without* inheriting the outer `-d memory_limit=-1`, so it OOMs at the 128 MB default (fatal in `filament/.../routes/web.php` during `setUp()`). Invoking `vendor/bin/pest` directly keeps the run in one unlimited-memory process. Quality cmd `php artisan test` (root CLAUDE.md) is nominal; this is the memory-safe equivalent.

---

## [2026-06-23 10:16] — 1.1 ClubCreditState enum
- Implemented the `ClubCreditState` string enum (`active`/`redeemed`/`forfeited`) — the § 11 Club Credit FSM `active → redeemed | forfeited`. Two predicates: `isActive()` (live/value-mutable from-state for Apply + Forfeit guards) and `isTerminal()` (absolutely terminal ≡ `forfeited` only). Docblock pins the terminality nuance: `redeemed` is "terminal for forfeiture" but restore-reachable to `active`, so NOT absolutely terminal — only `forfeited` is.
- Files changed: `app/Modules/Parties/Enums/ClubCreditState.php` (new), `tests/Unit/Modules/Parties/Enums/ClubCreditEnumsTest.php` (new, 5 tests / 12 assn), `openspec/changes/club-credit/tasks.md` (1.1 → [x]).
- Quality loop: **green** — Pint clean; new test 5/5; full suite **1500/1500 (8275 assn)** via `vendor/bin/pest`; PHPStan max 0 err; `pint --test` clean; `openspec validate club-credit --strict` valid. (No DB touch → no PG17 lane this task.)
- **Learnings for future iterations:**
  - Both `Codebase Patterns` above were discovered here (state-enum idiom; the `vendor/bin/pest` memory-safe full-suite command). Next iteration (1.2 migration) is the first DB-touching task → must verify on PostgreSQL 17 too; the partial-unique-index precedent is `2026_06_15_000007_create_parties_profiles_table.php` (raw `DB::statement('CREATE UNIQUE INDEX … WHERE …')`, not driver-guarded).
  - `isActive()` is consumed by the Apply + Forfeit from-state guards (tasks 3.1/4.1); `isTerminal()` documents the FSM and is exercised by the forfeiture-terminality test (4.3). Use `$credit->state->isActive()` rather than `=== ClubCreditState::Active` in those Actions for readability parity with the siblings.
---
