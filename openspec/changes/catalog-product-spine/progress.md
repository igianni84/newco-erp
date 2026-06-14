# Progress — catalog-product-spine

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **House enum style (`App\Platform\*`, `App\Modules\Module`):** PascalCase case
  name, lowercase/snake_case backing value, rich docblock ending with the legend
  `- case name = … / - backing value = …`. Pure-vocabulary enums (`ActorRole`,
  `DeliveryMode`) are bare case lists — **no speculative helper methods** (YAGNI;
  add accessors only when a call-site needs them). Richer enums (`SupportedLocale`,
  `Currency`) add `fallback()`/`base()`/`assertSupported()` only because real
  call-sites use them. Catalog `ProductType`/`LifecycleState` follow the bare form.
- **Enum test convention (`tests/Unit/.../EnumsTest.php`):** pure unit test, **no
  `uses(RefreshDatabase)`**. Per enum: loop `Enum::cases()` into a `name => value`
  map and assert verbatim & order-sensitive with `->toBe([...])`; add an explicit
  `->toHaveCount(n)` length guard when "exactly N cases" is itself a spec rule;
  add a fail-closed `expect(fn () => Enum::from('bogus'))->toThrow(ValueError::class)`
  edge case. Mirrors `tests/Unit/Platform/EnumsTest.php`.
- **Spec-driven enum guards:** `ProductType::cases()` length-1 is the AC-0-XM-9
  "WINE is the only launch type" guard; `LifecycleState` carries the full
  four-state domain (PRD §4.1) though this slice transitions none of it — the
  enum exists so `catalog-lifecycle-approval` drives it without a migration.
- **PSR-4:** `App\Modules\Catalog\Enums\X` → `app/Modules/Catalog/Enums/X.php`
  (`App\` → `app/`); `Write` creates parent dirs, no autoload edit needed.

---

## [2026-06-14 19:23] — 1.1 Catalog enums (ProductType + LifecycleState)
- **What:** Added the two Catalog spine enums (design D2/D3) — `ProductType:string`
  (sole case `Wine='wine'`) and `LifecycleState:string` (`Draft/Reviewed/Active/
  Retired` → `draft/reviewed/active/retired`). Pure case-list enums with house-style
  docblocks citing Module 0 PRD §3.1/§16 + AC-0-XM-9 (ProductType) and §4.1/§14.2
  (LifecycleState). No DB this task.
- **Files changed:**
  - `app/Modules/Catalog/Enums/ProductType.php` (new)
  - `app/Modules/Catalog/Enums/LifecycleState.php` (new)
  - `tests/Unit/Modules/Catalog/Enums/EnumsTest.php` (new — 4 tests, 6 assertions)
  - `openspec/changes/catalog-product-spine/tasks.md` (1.1 checked)
  - (folded in the still-uncommitted authoring artifacts onto the branch: the ADR
    `decisions/2026-06-14-catalog-category-neutral-representation.md` + its
    `decisions/INDEX.md` row — every task's standing rule says to read the ADR, so
    it belongs on the branch with the first implementation commit.)
- **Quality loop:** green — pint clean · filtered test 4/4 · full suite **258/258**
  (910 assertions, was 254 pre-task) · phpstan **0 @ max** · pint --test clean ·
  `openspec validate catalog-product-spine --strict` valid · `git diff main --
  composer.{json,lock}` empty.
- **Learnings for future iterations:**
  - The bare-enum decision (no helpers) keeps PHPStan-max clean with zero effort and
    leaves the lifecycle change free to add transition logic where it belongs.
  - `->toHaveCount(1)` reads as the explicit WINE-only-at-launch contract even though
    the `toBe` map already implies it — keep it; it documents AC-0-XM-9 intent.
  - Next task **2.1 (Format)** is the **first DB-touching task** → the five
    SQLite↔PG17 portability traps engage (driver-guarded enum CHECK mirroring
    `domain_events`, assert json/TranslatableText by key, named test doubles) and the
    PG17 cross-engine run becomes mandatory before "done". It also establishes the
    `Events/` one-class-per-event convention + the `Create*` action + transactional
    `*Created` recorder pattern that 2.2–4.2 all repeat.
---
