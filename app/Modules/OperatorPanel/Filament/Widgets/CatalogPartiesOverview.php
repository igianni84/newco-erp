<?php

namespace App\Modules\OperatorPanel\Filament\Widgets;

use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\Profile;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * CatalogPartiesOverview — the dashboard's KPI band (operator-console UI pass, 2026-06-24). It replaces the
 * default Filament Account + Info widgets with six real headline counts drawn ONLY from the two modules shipped
 * so far — Catalog (Module 0) and Parties (Module K). Like the consoles, the OperatorPanel surface READS module
 * models for display (count() is a read; the no-Eloquent-write rule polices writes only). Brand-coloured
 * descriptions reuse the navigation-group labels; all copy is localized (invariant 12).
 */
class CatalogPartiesOverview extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $catalog = (string) __('operator_console.navigation_group.catalog');
        $parties = (string) __('operator_console.navigation_group.parties');

        return [
            Stat::make((string) __('operator_console.dashboard.stats.product_masters'), (string) ProductMaster::query()->count())
                ->description($catalog)
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),
            Stat::make((string) __('operator_console.dashboard.stats.sellable_skus'), (string) SellableSku::query()->count())
                ->description($catalog)
                ->descriptionIcon('heroicon-m-tag')
                ->color('primary'),
            Stat::make((string) __('operator_console.dashboard.stats.producers'), (string) Producer::query()->count())
                ->description($parties)
                ->descriptionIcon('heroicon-m-building-storefront'),
            Stat::make((string) __('operator_console.dashboard.stats.clubs'), (string) Club::query()->count())
                ->description($parties)
                ->descriptionIcon('heroicon-m-user-group'),
            Stat::make((string) __('operator_console.dashboard.stats.customers'), (string) Customer::query()->count())
                ->description($parties)
                ->descriptionIcon('heroicon-m-users'),
            Stat::make((string) __('operator_console.dashboard.stats.active_memberships'), (string) Profile::query()->where('state', 'active')->count())
                ->description($parties)
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}
