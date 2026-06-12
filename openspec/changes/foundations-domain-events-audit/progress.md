# Progress — foundations-domain-events-audit

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Platform substrate code lives under `App\Platform`** (design D1). Enums sit in `App\Platform\Events` (`app/Platform/Events/`). PSR-4 `App\` → `app/` already covers it — no composer change.
- **Enum convention** (mirrors `App\Modules\Module`): string-backed, PascalCase case name → snake_case backing value; a `Module`-density class docblock citing the design/spec source. Enum tests live in `tests/Unit/Platform/`, assert the full case→value map verbatim with `->toBe([...])` (order-sensitive), and add one `from('<bad>')` → `ValueError` failure case. Mirror `tests/Unit/Modules/ModuleTest.php`.
- **Boundary-law amendment for a NEW platform root** (the only legitimate edit to the hardcoded `$platformNamespaces` array): add the root string to `$platformNamespaces` in `tests/Architecture/ModuleBoundariesTest.php` with a design-citing comment, then **prove non-vacuity with a red-proof** — a temp class under the new root type-referencing a module symbol must turn the suite RED naming the exact pair, then removal returns it GREEN with a clean `git status`. This is mandatory (lessons.md 2026-06-12; design Risk "vacuously-green arch tests"). F1 3/3 adds `App\Platform\Money` etc. *under the same already-listed `App\Platform` root* — no further amendment needed once `App\Platform` is in the array.
- **pest-plugin-arch RED failure message format**: `Expecting '<source>' not to use '<target>'.` — useful to grep for when asserting a red-proof named the right pair.
- **Test runner emits JSON** (one line per run: `{"tool":"pest","result":...,"tests":N,...}`). A failure includes a `failures[]` array with `test`, `file`, `line`, `message`. Tail/grep accordingly; don't expect Pest's pretty TTY output.
- **Per-file `RefreshDatabase`**: the global Pest binding stays commented — DB-touching test files opt in with `uses(RefreshDatabase::class)` per-file (tasks.md standing rule). 1.1 needed no DB so EnumsTest is a pure unit test.

---

## [2026-06-12 14:26] — 1.1 Platform root + substrate enums + boundary-law amendment
- **What:** Created the `App\Platform` root with the three substrate enums and extended the platform-direction boundary law to cover it (design D1).
- **Files changed:**
  - `app/Platform/Events/ActorRole.php` — string-backed enum: `NewcoOps=newco_ops`, `Producer=producer`, `Customer=customer`, `System=system` (invariant-8 actor provenance).
  - `app/Platform/Events/DeliveryStatus.php` — `Pending=pending`, `Done=done`, `Failed=failed` (the `event_deliveries` lifecycle).
  - `app/Platform/Events/DeliveryMode.php` — single case `Inline=inline`; `queued` deliberately unrepresentable until the queue-driver ADR (F4–F6), making "queued is gated" a compile-time guarantee.
  - `tests/Unit/Platform/EnumsTest.php` — 3 verbatim case/value maps + 1 failure case (`ActorRole::from('admin')` → `ValueError`). 4 tests / 4 assertions.
  - `tests/Architecture/ModuleBoundariesTest.php` — added `'App\Platform'` to `$platformNamespaces` with a D1-citing comment.
- **Quality loop:** green — format ✅ · EnumsTest 4/4 ✅ · full suite **64/64 (323 assertions)** ✅ · phpstan level max 0 errors ✅ · pint --test ✅ · `openspec validate --strict` ✅.
- **RED-PROOF (mandatory, design D1 / lessons.md 2026-06-12):**
  - Added temp fixture `app/Platform/TempBoundaryProbe.php` (`namespace App\Platform; use App\Modules\Module;` returning `Module::Catalog`).
  - Ran `tests/Architecture/ModuleBoundariesTest.php` → **RED**: `it_forbids_platform_code_from_depending_on_any_module` failed with `Expecting 'App\Platform' not to use 'App\Modules'.` (tests 2, passed 1, failed 1) — the exact (source, target) pair, so the amendment is non-vacuous.
  - `rm app/Platform/TempBoundaryProbe.php` → re-ran the suite → **GREEN** (2 tests, 152 assertions). `git status app/` shows only `?? app/Platform/` (the three enums); the probe is gone, tree clean.
- **Learnings for future iterations:**
  - The boundary suite has high assertion counts (152) because both directions loop the registry — a single dirty namespace surfaces as one named pair, easy to read in the JSON `failures[]`.
  - `DeliveryMode` was made string-backed (`inline`) for parity with the other two and to keep the eventual `Queued` case persistable; nothing stores it yet (registry-time only).
  - No DB, no migration in this task — the first migrations arrive at 2.1. Kept the enums method-free; casts/usage land in 3.1 (models) per design D2.
---
