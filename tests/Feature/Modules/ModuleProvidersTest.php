<?php

use App\Modules\Module;

// Pins the module wiring seam (foundations-modules-skeleton, task 1.2; design
// D1/D2): every module owns a standard service provider, registered at boot
// and living under app/Modules/{Name}/Providers. The test iterates the
// canonical registry — never a hardcoded list — so a module whose provider is
// missing or unregistered fails here.

it('registers a standard service provider for every module at boot', function () {
    $providers = array_map(
        fn (Module $module) => $module->providerClass(),
        Module::cases(),
    );

    expect(app()->getLoadedProviders())->toHaveKeys($providers);
});

it('keeps each module provider autoloadable under its module Providers directory', function () {
    foreach (Module::cases() as $module) {
        $provider = $module->providerClass();
        $path = app_path("Modules/{$module->name}/Providers/{$module->name}ServiceProvider.php");

        expect(class_exists($provider))->toBeTrue()
            ->and(file_exists($path))->toBeTrue();
    }
});

it('does not register a provider for a module outside the registry', function () {
    expect(app()->getLoadedProviders())
        ->not->toHaveKey('App\Modules\Warehouse\Providers\WarehouseServiceProvider');
});
