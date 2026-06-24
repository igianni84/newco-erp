<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\SupplierResource\Pages;

use App\Modules\OperatorPanel\Filament\Resources\Parties\SupplierResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

/**
 * The read Supplier list (operator-console UI pass, 2026-06-24).
 *
 * The single header affordance is a navigation LINK to the dedicated write-through Create page — deliberately
 * NOT a Filament CreateAction, whose inline-modal path does `new Model; $record->save()` and would bypass the
 * Parties domain action (ADR 2026-06-19; the no-Eloquent-write rule). A plain url() action renders as a link:
 * creation routes through {@see CreateSupplier}.
 */
class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label((string) __('operator_console.supplier.actions.create'))
                ->url(SupplierResource::getUrl('create')),
        ];
    }
}
