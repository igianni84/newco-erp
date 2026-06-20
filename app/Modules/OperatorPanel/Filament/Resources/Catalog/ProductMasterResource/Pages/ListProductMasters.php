<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages;

use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

/**
 * The read Product Master list (operator-console-catalog-master, task 2.1/3.1; design L1/L2/L10).
 *
 * The single header affordance is a navigation LINK to the dedicated write-through Create page — deliberately
 * NOT a Filament CreateAction, whose inline-modal path does `new Model; $record->fill()->save()` and would
 * bypass the Catalog domain action (ADR 2026-06-19; design L2; the no-Eloquent-write rule, task 1.2). A plain
 * url() action renders as a link: no modal, no model write — creation routes through CreateProductMaster.
 */
class ListProductMasters extends ListRecords
{
    protected static string $resource = ProductMasterResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label((string) __('operator_console.product_master.actions.create'))
                ->url(ProductMasterResource::getUrl('create')),
        ];
    }
}
