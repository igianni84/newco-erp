<?php

namespace App\Modules\Parties\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Parties module service provider — the standard wiring seam (design D1,
 * foundations-modules-skeleton). Routes, translations, event listeners and
 * container bindings for this bounded context land here as the module grows;
 * empty at the skeleton stage and required by registry conformance.
 */
class PartiesServiceProvider extends ServiceProvider
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
