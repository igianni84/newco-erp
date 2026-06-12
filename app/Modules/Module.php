<?php

namespace App\Modules;

/**
 * Canonical registry of the nine bounded-context modules (design D2).
 *
 * The single machine-readable source of truth for "the nine modules": the
 * architecture tests iterate Module::cases() — never a hardcoded list — and
 * the next change anchors the domain-event envelope's `module` field here.
 *
 * - case name  = the module namespace segment (App\Modules\{CaseName}, design D1)
 * - backing value = the snake_case table prefix (design D6, invariant 10)
 * - letter()   = the spec module letter (CLAUDE.md "Canonical Terminology" table)
 */
enum Module: string
{
    case Catalog = 'catalog';
    case Parties = 'parties';
    case Allocation = 'allocation';
    case Procurement = 'procurement';
    case Commerce = 'commerce';
    case Inventory = 'inventory';
    case Fulfilment = 'fulfilment';
    case Finance = 'finance';
    case OperatorPanel = 'operator_panel';

    /**
     * The spec module letter per the CLAUDE.md "Canonical Terminology" table:
     * Catalog=0, Parties=K, Allocation=A, Procurement=D, Commerce=S,
     * Inventory=B, Fulfilment=C, Finance=E, OperatorPanel=Admin.
     */
    public function letter(): string
    {
        return match ($this) {
            self::Catalog => '0',
            self::Parties => 'K',
            self::Allocation => 'A',
            self::Procurement => 'D',
            self::Commerce => 'S',
            self::Inventory => 'B',
            self::Fulfilment => 'C',
            self::Finance => 'E',
            self::OperatorPanel => 'Admin',
        };
    }

    /**
     * The module's PHP namespace root, e.g. App\Modules\Catalog (design D1).
     */
    public function namespace(): string
    {
        return __NAMESPACE__.'\\'.$this->name;
    }

    /**
     * The module's standard service-provider FQCN, e.g.
     * App\Modules\Catalog\Providers\CatalogServiceProvider (design D1).
     *
     * The wiring seam registered in bootstrap/providers.php. OperatorPanel's
     * Filament panel is provided separately by AdminPanelProvider (design D5),
     * not by this standard provider.
     */
    public function providerClass(): string
    {
        return $this->namespace().'\\Providers\\'.$this->name.'ServiceProvider';
    }
}
