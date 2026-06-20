<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages;

use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource;
use Filament\Resources\Pages\ListRecords;

/**
 * The read-only Product Master list (operator-console-catalog-master, task 2.1; design L1/L10).
 *
 * No header CreateAction: creation is a dedicated write-through Create page wired to CreateProductMaster
 * (task 3.1), never a default Filament mutating path (ADR 2026-06-19; design L2). getHeaderActions()
 * defaults to empty, so adding nothing here leaves the surface read-only.
 */
class ListProductMasters extends ListRecords
{
    protected static string $resource = ProductMasterResource::class;
}
