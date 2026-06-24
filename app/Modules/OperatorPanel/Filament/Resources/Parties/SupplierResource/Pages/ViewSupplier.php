<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\SupplierResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleViewRecord;
use App\Modules\OperatorPanel\Filament\Resources\Parties\SupplierResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * The read-only Supplier view page (operator-console UI pass, 2026-06-24).
 *
 * Supplier has NO lifecycle (no status/version — § 4.5), so unlike the other Parties consoles this view does
 * NOT extend the lifecycle-verb kit {@see OperatorConsoleViewRecord};
 * it is a plain read of the resource infolist with no header actions.
 */
class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;
}
