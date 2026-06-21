<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages;

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

/**
 * The read Customer list (operator-console-parties-customer, task 1.2; design D7).
 *
 * The single header affordance is a navigation LINK to the dedicated write-through Create page — deliberately
 * NOT a Filament CreateAction, whose inline-modal path does `new Model; $record->fill()->save()` and would
 * bypass the Parties domain action (ADR 2026-06-19; the no-Eloquent-write rule). A plain url() action renders
 * as a link: no modal, no model write — creation routes through {@see CreateCustomer}.
 */
class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label((string) __('operator_console.customer.actions.create'))
                ->url(CustomerResource::getUrl('create')),
        ];
    }
}
