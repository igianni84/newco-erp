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
