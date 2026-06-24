<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\RelationManagers;

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * MembershipsRelationManager — a Customer's Club memberships (Profiles), surfaced READ-ONLY as a sub-table on
 * the Customer's view page (operator-console UI pass, 2026-06-24). A Customer holds zero-or-more memberships,
 * one per Club (the Netflix-style model); this shows them in the Customer's own context.
 *
 * Read-only by design: a membership is CREATED as an application through the cross-Customer approval queue
 * ({@see ProfileResource} — renamed "Memberships" in the sidebar) and advanced through its own lifecycle verbs,
 * not ad-hoc from a Customer — so there is no create action here. Columns are reused from
 * {@see ProfileResource::table()}; the row View action links to the membership view page. All copy is localized
 * (invariant 12).
 */
class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'profiles';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return (string) __('operator_console.relations.memberships');
    }

    public function table(Table $table): Table
    {
        return ProfileResource::table($table)
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Model $record): string => ProfileResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
