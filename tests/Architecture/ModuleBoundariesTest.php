<?php

use App\Modules\Module;

/**
 * The cross-module imports a given source module is permitted — the namespaces
 * fed to ->ignoring() in the privacy law below, and the single source of truth
 * the carve-out guard test re-checks so the exception cannot widen by accident.
 *
 * Baseline (every module): each OTHER module's public surface — its Contracts\*
 * and Events\* sub-namespaces (the modular-monolith ADR's "domain events plus
 * narrow read contracts", made mechanical).
 *
 * OperatorPanel carve-out — the operator-console read-binding / write-through
 * exception (decisions/2026-06-19-operator-console-read-binding-write-through-actions.md;
 * design L1/L2). OperatorPanel is module #9 but it is the top-down COMPOSITION
 * layer, not a lateral peer: it owns no entities and exists to operate the
 * others'. So its Filament consoles may ALSO import each operated module's:
 *   - Models\*  — read-bind a resource to the real Eloquent model, read-only.
 *     The no-Eloquent-write PHPStan rule (tests/PHPStan, task 1.2) enforces the
 *     read-only half, so this import test no longer carries that weight here.
 *   - Actions\* — every mutation routes through app(<Action>)->handle(...), so a
 *     console references the owning module's domain actions (e.g.
 *     app(CreateProductMaster::class) — the seven Catalog lifecycle actions).
 * Consoles catch domain exceptions via base types and render enum casts via
 * their instances, so the cross-module surface stays exactly {Models, Actions}
 * and no later console task needs to widen this list.
 *
 * Strictly scoped to OperatorPanel: a lateral business module (e.g. Catalog)
 * gets the baseline public surface ONLY, so a Catalog -> Parties\Models or
 * Catalog -> Parties\Actions import stays a boundary violation. The carve-out
 * guard test pins both directions.
 *
 * @return list<string>
 */
function moduleBoundaryAllowedImports(Module $source): array
{
    $allowed = [];

    foreach (Module::cases() as $other) {
        if ($other === $source) {
            continue;
        }

        $allowed[] = $other->namespace().'\\Contracts';
        $allowed[] = $other->namespace().'\\Events';

        if ($source === Module::OperatorPanel) {
            $allowed[] = $other->namespace().'\\Models';
            $allowed[] = $other->namespace().'\\Actions';
        }
    }

    return $allowed;
}

// Pins the cross-module privacy law (foundations-modules-skeleton, task 2.2;
// design D3 — "the boundary law"). For every module M, code under
// App\Modules\{M} must not use any OTHER module's namespace EXCEPT the imports
// moduleBoundaryAllowedImports() permits: that module's public surface
// (Contracts\* + Events\*), plus the OperatorPanel carve-out (the monolith
// ADR's "domain events plus narrow read contracts" made mechanical). Module
// internals are private; cross-module coupling is a reviewable failure from day
// zero, before any code exists that could violate it.
//
// Both sides iterate Module::cases() — never a hardcoded list — so the law
// covers a tenth module the instant it joins the registry, and the source
// module is always one of the enum's own namespaces.
//
// Mechanics (verified against vendor/pestphp/pest-plugin-arch v4.0.2, design
// D4 — never write arch expectations from memory):
//   - not->toUse([...others]) fails if the source uses ANY listed namespace, and
//     the failure names the exact (source, target) pair — so one expectation per
//     source module is enough to localise the offender (LayerFactory builds the
//     dependency layer by name-prefix; ToUse reports the first violating pair).
//   - ->ignoring([...]) strips uses by namespace PREFIX before the check
//     (LayerFactory::make → str_starts_with), so a reference into another
//     module's Contracts\* / Events\* (or, for OperatorPanel, Models\* /
//     Actions\*) is allowed while a reference into anything else under that
//     module's namespace is still a violation.
//
// OperatorPanel carve-out (ADR 2026-06-19): the operator-console composition
// layer may additionally import each operated module's Models\* (read-bind) and
// Actions\* (write-through via app(<Action>)->handle()). It lives in
// moduleBoundaryAllowedImports() and is pinned OperatorPanel-only by the
// carve-out guard test below; the no-Eloquent-write rule (task 1.2) carries the
// read-only half this import test no longer enforces for OperatorPanel.
//
// The composition root (bootstrap/providers.php) is outside app/ classes and so
// is naturally out of scope; string-based references (Filament path discovery)
// carry no symbol import and are invisible to this analysis by design (D3/D5).

it('keeps each module private to every other module except its public surface', function () {
    foreach (Module::cases() as $module) {
        $others = array_values(array_filter(
            Module::cases(),
            fn (Module $other) => $other !== $module,
        ));

        // Forbidden: every other module's namespace as a whole...
        $forbidden = array_map(fn (Module $other) => $other->namespace(), $others);

        // ...except the imports allowed for this source module: every other
        // module's public surface (Contracts\* + Events\*) plus, for the
        // OperatorPanel composition layer only, each operated module's Models\*
        // and Actions\* (the operator-console carve-out — see the helper).
        expect($module->namespace())
            ->not->toUse($forbidden)
            ->ignoring(moduleBoundaryAllowedImports($module));
    }
});

// Pins the operator-console carve-out to the OperatorPanel namespace ONLY
// (ADR 2026-06-19; design L1) — the guard the task calls "plant one to prove it
// still fails", made permanent and layout-independent. It asserts directly on
// moduleBoundaryAllowedImports() (the same source of truth the law above feeds
// to ->ignoring()), so widening the carve-out to a lateral module — or to a
// whole module instead of just Models\* + Actions\* — breaks this test:
//   - OperatorPanel MAY read-bind + write-through every operated module, but its
//     allow-list never contains a whole-module namespace nor a module internal
//     (e.g. Catalog\Lifecycle), so it cannot reach past Models\* / Actions\*.
//   - a lateral business module (Catalog) gets the public surface ONLY, so a
//     Catalog -> Parties\Models / Parties\Actions import stays forbidden.
// Catalog and Parties are named as the concrete lateral pair the acceptance
// cites; the property holds for any non-OperatorPanel source.

it('scopes the operator-console Models/Actions carve-out to OperatorPanel only', function () {
    $operatorPanelAllows = moduleBoundaryAllowedImports(Module::OperatorPanel);

    foreach (Module::cases() as $operated) {
        if ($operated === Module::OperatorPanel) {
            continue;
        }

        // Read-binding + write-through is permitted for every operated module...
        expect(in_array($operated->namespace().'\\Models', $operatorPanelAllows, true))->toBeTrue();
        expect(in_array($operated->namespace().'\\Actions', $operatorPanelAllows, true))->toBeTrue();
        // ...but never the whole module, so console code cannot reach a module
        // internal (Lifecycle, Services, Enums, Exceptions, ...).
        expect(in_array($operated->namespace(), $operatorPanelAllows, true))->toBeFalse();
    }

    // A lateral business module gets the public surface ONLY — its peers'
    // Models\* / Actions\* stay forbidden, so the carve-out cannot be widened by
    // accident into general cross-module coupling.
    $catalogAllows = moduleBoundaryAllowedImports(Module::Catalog);
    expect(in_array('App\\Modules\\Parties\\Contracts', $catalogAllows, true))->toBeTrue();
    expect(in_array('App\\Modules\\Parties\\Events', $catalogAllows, true))->toBeTrue();
    expect(in_array('App\\Modules\\Parties\\Models', $catalogAllows, true))->toBeFalse();
    expect(in_array('App\\Modules\\Parties\\Actions', $catalogAllows, true))->toBeFalse();
});

// Pins the platform-direction half of the boundary law (task 2.3; design D3 —
// the table's second row). Platform code — every App\* namespace OUTSIDE
// App\Modules — is the substrate the modules sit on, so it must never depend on
// a module. Unlike the module-to-module law above there is NO public-surface
// exemption: platform code may not reference even another module's Contracts\* /
// Events\* (D3 "Platform code" row: Contracts/Events = NO, internals = NO), so
// the dependency target is App\Modules as a WHOLE and there is no ->ignoring().
// The composition root bootstrap/providers.php is exempt by design — it lives
// under bootstrap/, outside app/, so it is in none of the App\* source layers.
//
// The platform namespaces are the complement of the Module registry (the
// non-module App\* roots) and so cannot be derived from Module::cases(); they
// are enumerated in the single named array below. Extend $platformNamespaces
// when a new platform root appears — the module-build template cites this as the
// amendment point for the law's platform side.
//
// We LOOP and assert one source per expectation — expect($ns)->not->toUse(...) —
// and deliberately NOT expect([all])->not->toUse(...). Verified against vendor
// (design D4, empirically probed): an array on the SOURCE side under `not`
// inverts the AGGREGATE "all sources use X", so expect([a,b,c])->not->toUse(X)
// passes the moment ONE source is clean — it would miss a single dirty platform
// namespace. Per-source `not` reads "this source uses none of X" and fails the
// instant any one source imports a module, which is the law we need. (An array on
// the DEPENDENCY side — the module-privacy test's $forbidden — is the safe
// direction: there it means "uses ANY of these".)

it('forbids platform code from depending on any module', function () {
    $platformNamespaces = [
        'App\\Providers',
        'App\\Models',
        'App\\Http',
        // foundations-domain-events-audit design D1 (amendment protocol, skeleton
        // design D7): the domain-event + audit substrate is platform code —
        // modules depend on it (they call the recorders and implement the consumer
        // contract), so it must never depend on a module. Its root joins the
        // platform-never-imports-modules direction here; non-vacuity proven by the
        // task 1.1 red-proof recorded in progress.md.
        'App\\Platform',
    ];

    foreach ($platformNamespaces as $platformNamespace) {
        expect($platformNamespace)->not->toUse('App\\Modules');
    }
});
