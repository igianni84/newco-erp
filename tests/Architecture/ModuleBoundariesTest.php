<?php

use App\Modules\Module;

// Pins the cross-module privacy law (foundations-modules-skeleton, task 2.2;
// design D3 — "the boundary law"). For every module M, code under
// App\Modules\{M} must not use any OTHER module's namespace EXCEPT that module's
// public surface: its Contracts\* and Events\* sub-namespaces (the monolith
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
//     module's Contracts\* / Events\* is allowed while a reference into anything
//     else under that module's namespace is still a violation.
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

        // ...except each other module's public surface (Contracts\* + Events\*).
        $publicSurface = [];
        foreach ($others as $other) {
            $publicSurface[] = $other->namespace().'\\Contracts';
            $publicSurface[] = $other->namespace().'\\Events';
        }

        expect($module->namespace())
            ->not->toUse($forbidden)
            ->ignoring($publicSurface);
    }
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
