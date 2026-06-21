<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages;

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * ViewCustomer — the Customer console view page (operator-console-parties-customer; design D1).
 *
 * Scaffolded BARE in task 1.2 so the resource's `getPages()` boots (the eager page-reference coupling — design
 * Risks): a `ViewRecord` renders the read-only infolist that lives on {@see CustomerResource}, with no header
 * actions yet. Task 3.1 fills it with the status-FSM verb set (activate / suspend / reactivate / close) the
 * non-catalog way — `use SurfacesDomainActions` + a bespoke `getHeaderActions()`, NOT the catalog-shaped
 * `OperatorConsoleViewRecord` base (design D1/D8) — so this stub is deliberately minimal and is replaced, not
 * extended, there.
 */
class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;
}
