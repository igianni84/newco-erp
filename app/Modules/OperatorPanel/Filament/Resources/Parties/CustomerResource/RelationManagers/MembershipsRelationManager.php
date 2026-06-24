<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\RelationManagers;

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
            // Filter the membership rows by their lifecycle `state` — a real Profile column (`parties_profiles.state`).
            // Options are derived from the DISTINCT raw tokens present (a `pluck`, so the cast is NOT applied — the
            // value is the raw string) and humanized, mirroring the kit's stateFilter() so the relation manager needs
            // no `Parties\Enums\ProfileState` import (the {Models, Actions} read surface — design D2). `pluck` is a
            // read; the no-Eloquent-write rule polices writes only.
            ->filters([
                SelectFilter::make('state')
                    ->label((string) __('operator_console.profile.columns.state'))
                    ->options(fn (): array => ProfileResource::getModel()::query()
                        ->select('state')
                        ->distinct()
                        ->orderBy('state')
                        ->pluck('state')
                        ->mapWithKeys(function (mixed $value): array {
                            $token = match (true) {
                                $value instanceof BackedEnum => (string) $value->value,
                                is_string($value) => $value,
                                default => '',
                            };

                            return [$token => Str::headline($token)];
                        })
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Model $record): string => ProfileResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
