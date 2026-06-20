<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages;

use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * The read-only Product Master view — the neutral core plus the WINE attribute set, rendered from the
 * resource infolist (operator-console-catalog-master, task 2.1; design L1/L10).
 *
 * No header EditAction: the Catalog backend ships no update Action, so there are no field edits here;
 * the lifecycle transitions (submit/reject/activate/retire/cascade/reopen) land as their own write-through
 * Actions in tasks 4–5 (ADR 2026-06-19; design L2). getHeaderActions() defaults to empty.
 */
class ViewProductMaster extends ViewRecord
{
    protected static string $resource = ProductMasterResource::class;
}
