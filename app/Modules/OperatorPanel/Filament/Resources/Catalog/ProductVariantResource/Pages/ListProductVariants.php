<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages;

use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

/**
 * The read Product Variant list (operator-console-catalog-spine, task 3.1; design L1/L2).
 *
 * The single header affordance is a navigation LINK to the dedicated write-through Create page — deliberately
 * NOT a Filament CreateAction, whose inline-modal path does `new Model; $record->fill()->save()` and would
 * bypass the Catalog domain action (ADR 2026-06-19; the no-Eloquent-write rule, task 1.2). A plain url()
 * action renders as a link: no modal, no model write — creation routes through {@see CreateProductVariant}.
 */
class ListProductVariants extends ListRecords
{
    protected static string $resource = ProductVariantResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label((string) __('operator_console.product_variant.actions.create'))
                ->url(ProductVariantResource::getUrl('create')),
        ];
    }
}
