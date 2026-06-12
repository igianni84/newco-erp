<?php

use App\Modules\Module;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    // The nine module wiring seams, derived from the canonical registry
    // (design D1/D2) — never a hardcoded list, so module-set drift is loud.
    ...array_map(
        fn (Module $module) => $module->providerClass(),
        Module::cases(),
    ),
];
