<?php

namespace App\Modules\OperatorPanel\Filament\Clusters;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use BackedEnum;
use Filament\Clusters\Cluster;
use UnitEnum;

/**
 * CatalogSettings — the "Settings" cluster that groups the Catalog reference / building-block consoles
 * (Format, Case Configuration, Product Reference) under ONE sidebar entry whose sub-navigation renders them
 * as tabs (operator-console UI pass, 2026-06-24). The sellable / browseable consoles — Product Master (with
 * its nested Variants), Sellable SKU and Composite SKU — stay top-level in the Catalog group; this cluster
 * holds only the lower-level reference data an operator configures less often, decluttering the sidebar for
 * the demo.
 *
 * The cluster itself sits in the Catalog navigation group (so "Settings" reads as PART OF Catalog, not a peer
 * of Catalog / Parties); the clustered resources override getNavigationGroup() to null so the cluster's
 * sub-navigation renders as a flat tab strip rather than re-nesting a "Catalog" sub-heading.
 */
class CatalogSettings extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 90;

    public static function getNavigationLabel(): string
    {
        return (string) __('operator_console.cluster.catalog_settings');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return (string) __('operator_console.cluster.catalog_settings');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return OperatorConsoleNavigationGroup::Catalog;
    }
}
