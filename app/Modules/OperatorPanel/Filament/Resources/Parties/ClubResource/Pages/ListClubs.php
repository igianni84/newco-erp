<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource\Pages;

use App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

/**
 * The read Club list (operator-console-parties-supply-side, task 2.2; design D6).
 *
 * The single header affordance is a navigation LINK to the dedicated write-through Create page — deliberately
 * NOT a Filament CreateAction, whose inline-modal path does `new Model; $record->fill()->save()` and would
 * bypass the Parties domain action (ADR 2026-06-19; the no-Eloquent-write rule). A plain url() action renders
 * as a link: no modal, no model write — creation routes through {@see CreateClub}.
 */
class ListClubs extends ListRecords
{
    protected static string $resource = ClubResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label((string) __('operator_console.club.actions.create'))
                ->url(ClubResource::getUrl('create')),
        ];
    }
}
