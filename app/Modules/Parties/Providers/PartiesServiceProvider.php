<?php

namespace App\Modules\Parties\Providers;

use App\Modules\Parties\Contracts\PartyComplianceStatusReader;
use App\Modules\Parties\Reads\DatabaseComplianceStatusReader;
use Illuminate\Support\ServiceProvider;

/**
 * Parties module service provider — the standard wiring seam (design D1,
 * foundations-modules-skeleton). Routes, translations, event listeners and
 * container bindings for this bounded context land here as the module grows.
 */
class PartiesServiceProvider extends ServiceProvider
{
    /**
     * Register any module services.
     */
    public function register(): void
    {
        // The Hold/sanctions read contract (parties-holds, design L6; party-registry — Requirement: Hold and
        // Sanctions Read-API; DEC-181). Module K's single cross-module compliance surface: downstream
        // transaction-initiation surfaces (Module S/C/E — deferred) resolve PartyComplianceStatusReader and
        // receive the PII-free ComplianceStatus DTO, never the Hold model (the no-model-leak boundary law). Bound
        // to the cascade-resolving database implementation; the reader is stateless, so a plain bind suffices.
        $this->app->bind(PartyComplianceStatusReader::class, DatabaseComplianceStatusReader::class);
    }

    /**
     * Bootstrap any module services.
     */
    public function boot(): void
    {
        //
    }
}
