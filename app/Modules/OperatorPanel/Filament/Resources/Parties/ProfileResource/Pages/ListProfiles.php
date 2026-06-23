<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages;

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * The read Profile list — the cross-Customer MEMBERSHIP APPROVAL QUEUE (operator-console-parties-membership,
 * task 1.2; design D3). The list is the operationally central surface: its FIRST tab, "Pending", is the default
 * active tab and filters to the `applied` Profiles awaiting an approve/decline decision (the queue); the "All"
 * tab drops the filter and shows every membership state.
 *
 * The tab filter is a literal `where('state', 'applied')` — the persisted `ProfileState::Applied` backing token —
 * never an imported `Parties\Enums` symbol (the {Models, Actions} read surface — design D2). No create header
 * affordance lands here in this group: the write-through create surface ({@see CreateProfile}) and its header link
 * arrive with the create slice (group 2); the lifecycle verbs live on {@see ViewProfile}.
 */
class ListProfiles extends ListRecords
{
    protected static string $resource = ProfileResource::class;

    /**
     * The approval-queue tabs: "Pending" (the default — `applied` Profiles awaiting a decision) and "All" (every
     * state). The first key is the default active tab (Filament `getDefaultActiveTab()`), so the queue opens on
     * Pending. The filter compares the persisted `state` token literally (`applied`), keeping the read path free
     * of any `Parties\Enums` import (design D2).
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make()
                ->label((string) __('operator_console.profile.tabs.pending'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('state', 'applied')),
            'all' => Tab::make()
                ->label((string) __('operator_console.profile.tabs.all')),
        ];
    }
}
