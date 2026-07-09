<?php

namespace App\Modules\Parties\Providers;

use App\Modules\Parties\Contracts\CustomerTransactionTotalsReader;
use App\Modules\Parties\Contracts\HeroPackageCapacityReader;
use App\Modules\Parties\Contracts\PartyComplianceStatusReader;
use App\Modules\Parties\Reads\ConfigHeroPackageCapacityReader;
use App\Modules\Parties\Reads\DatabaseComplianceStatusReader;
use App\Modules\Parties\Reads\NullCustomerTransactionTotalsReader;
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

        // The Module-S transaction-totals seam for enhanced-KYC threshold detection (parties-enhanced-kyc-threshold,
        // design D4; party-registry — Requirement: Enhanced-KYC Threshold Detection; DEC-035). The real reader reads
        // Module S (Commerce) order/invoice EUR history and is DEFERRED — Module S is a Phase-4 stub — so the interface
        // binds to the zero-returning NullCustomerTransactionTotalsReader at launch: the periodic scan runs and
        // detection is a correct no-op until Module S ships the real adapter. Stateless, so a plain bind suffices.
        $this->app->bind(CustomerTransactionTotalsReader::class, NullCustomerTransactionTotalsReader::class);

        // The Module-A capacity seam for the Hero-Package seat gate (parties-hero-package, design D1/D2;
        // party-registry — Requirement: Hero Package Capacity Is Read from Module A, Never Stored in Module K;
        // canon MVP-DEC-020). The capacity is the Hero-Package Allocation's qty, owned by Module A — a two-file
        // stub — so the interface binds to the config-backed ConfigHeroPackageCapacityReader at launch, which
        // reads config/parties.php (null ⇒ uncapped ⇒ the gate dark-launches). Module K stores no capacity value
        // of any kind (AC-K-XM-20); when Module A lands, ONLY this line changes. Stateless, so a plain bind suffices.
        $this->app->bind(HeroPackageCapacityReader::class, ConfigHeroPackageCapacityReader::class);
    }

    /**
     * Bootstrap any module services.
     */
    public function boot(): void
    {
        //
    }
}
