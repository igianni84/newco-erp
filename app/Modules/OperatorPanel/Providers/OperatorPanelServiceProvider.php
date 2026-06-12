<?php

namespace App\Modules\OperatorPanel\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * OperatorPanel module service provider — the standard wiring seam (design D1,
 * foundations-modules-skeleton). The Filament panel itself is provided
 * separately by AdminPanelProvider (design D5); this is the standard module
 * provider every module owns. Empty at the skeleton stage and required by
 * registry conformance.
 */
class OperatorPanelServiceProvider extends ServiceProvider
{
    /**
     * Register any module services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any module services.
     */
    public function boot(): void
    {
        //
    }
}
