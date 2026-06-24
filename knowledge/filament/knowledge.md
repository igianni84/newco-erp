# Filament — Knowledge (observed facts & patterns)

> Filament operator-panel insights: how read-projection Resources, write-through pages and `lifecycleAction` header verbs are built on the shared `app/Modules/OperatorPanel/Filament/Console/` kit. Promotion lifecycle: this file (observed) → `hypotheses.md` (Confirmations: N/3) → `rules.md` (apply by default). Sibling concerns: module-boundary law in `knowledge/architecture/`, Eloquent/runtime idioms in `knowledge/laravel/`, cross-engine test portability in `knowledge/testing/`.

Domain created **2026-06-24** (`operator-console-parties-membership`) to give the operator-console construction recipe — proven across 12 Resources / 8 console slices — a first-class home as the operator surface grows past Parties into the remaining modules.

(Observed-but-not-yet-promoted facts accrue here; confirmed-but-not-yet-rule patterns live in `hypotheses.md`. The established slice recipe is already in `rules.md`.)

## Ordered, localized sidebar groups = a `HasLabel` enum returned from `getNavigationGroup()`

The operator panel groups its consoles by spec module via `OperatorConsoleNavigationGroup` — a string-backed enum in `app/Modules/OperatorPanel/Filament/Console/` implementing `Filament\Support\Contracts\HasLabel`, returned from each Resource's `getNavigationGroup()`. Two properties fall out, both better than registering translated group strings in the panel provider:

- **Order** = enum `cases()` declaration order (Catalog before Parties). Filament's `NavigationManager` sorts enum-keyed groups by `array_search($case, $case::cases())` — locale-independent, so **no `->navigationGroups([...])` registration is needed**. (Registering translated strings is the fragile alternative: the panel-build locale and the render locale can differ, dropping the group to the end.)
- **Label** = `getLabel()` → `__('operator_console.navigation_group.<case>')`, resolved at render time in the operator's locale. Unlike English-invariant entity labels (Product Master, Customer…), module names localize: Catalog→"Catalogo", Parties→"Anagrafiche".

Wired through the shared base: `OperatorConsoleResource` declares an **abstract** `navigationGroupCase()` (mirroring `i18nKey()`) and implements `getNavigationGroup()` once; each Resource adds a one-line case + an integer `$navigationSort` for within-group order. The abstract method makes an ungrouped console structurally impossible (won't compile) — the regression guard against Filament's flat-alphabetical default. Locked by `OperatorConsoleNavigationTest` (group + sort per Resource, enum order, EN/IT labels).

**Observed (dated).** 2026-06-24 `operator-console-navigation-grouping` — the 12 consoles (previously no `navigationGroup` anywhere → one flat alphabetical list) regrouped into Catalog (Module 0, 7 entries) + Parties (Module K, 5). Spec is silent on navigation IA, so this is a free-but-now-locked product/IA decision, not a spec requirement. Panel-level complement to the per-slice construction recipe in `rules.md`; a future module console adds its case to the enum, in display order.
