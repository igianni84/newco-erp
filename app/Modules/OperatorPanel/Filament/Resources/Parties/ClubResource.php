<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource\Pages;
use App\Modules\Parties\Models\Club;
use BackedEnum;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ClubResource — the operator console's READ-ONLY surface over the Parties Club (operator-console-parties-
 * supply-side, task 2.1; design D2/D6/D7; ADR 2026-06-19 + 2026-06-20 + 2026-06-21). The SECOND console of the
 * Parties (Module K) supply-side, built — like {@see ProducerResource} — as the non-catalog trait-reuse pattern
 * (the View page assembles its own verb set; see `ClubResource\Pages\ViewClub`).
 *
 * It extends {@see OperatorConsoleResource} for the read-only conventions every console shares — the
 * `operator_console.<entity>` model labels off {@see i18nKey()} and the optimistic-lock `version` column helper.
 * It does NOT call the kit's `lifecycleStateColumn()`: a Club's state attribute is `status` (a `ClubStatus`,
 * `active → sunset → closed`), not the catalog `lifecycle_state` — so this resource supplies its OWN `status`
 * badge column (design D2), rendering the state enum through its cast `->value` and never importing
 * `App\Modules\Parties\Enums\ClubStatus` (the {Models} surface for the read path). `registration_flow_type`
 * (a `ClubRegistrationFlowType`, a fixed per-Club classifier, not a lifecycle state) renders the same way.
 *
 * It read-binds to {@see Club} — the ADR-sanctioned exception, OperatorPanel-only and display-only: the resource
 * queries the model (and its WITHIN-Parties `producer()` relation) for the list table + the view infolist and
 * NEVER writes it. Every mutation is a separate Filament Action routed through a Parties domain action (the kit's
 * create page + the bespoke view page); there is deliberately NO Edit page and NO Delete/Create default action —
 * the Parties backend ships no Club update Action (post-creation field edits are out of scope, proposal
 * slice-boundary), and create lands on a write-through {@see Pages\CreateClub} page that constructs the
 * `ClubRegistrationFlowType` OPERAND enum (admitted by the {Models, Actions, Enums} carve-out — ADR 2026-06-21).
 * The no-Eloquent-write PHPStan rule guards the discipline. All user-facing copy is localized through the
 * `operator_console` group (invariant 12).
 */
class ClubResource extends OperatorConsoleResource
{
    protected static ?string $model = Club::class;

    protected static ?string $recordTitleAttribute = 'display_name';

    protected static function i18nKey(): string
    {
        return 'club';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label((string) __('operator_console.club.columns.display_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('producer')
                    ->label((string) __('operator_console.club.columns.producer'))
                    ->getStateUsing(fn (Club $record): string => $record->producer->name),
                static::registrationFlowTypeColumn(),
                static::statusColumn(),
                static::versionColumn(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('display_name')
                    ->label((string) __('operator_console.club.columns.display_name')),
                TextEntry::make('producer')
                    ->label((string) __('operator_console.club.columns.producer'))
                    ->getStateUsing(fn (Club $record): string => $record->producer->name),
                TextEntry::make('registration_flow_type')
                    ->label((string) __('operator_console.club.columns.registration_flow_type'))
                    ->getStateUsing(function (Model $record): string {
                        $state = $record->getAttribute('registration_flow_type');

                        return $state instanceof BackedEnum ? (string) $state->value : '';
                    }),
                TextEntry::make('fee')
                    ->label((string) __('operator_console.club.fields.fee'))
                    ->getStateUsing(function (Club $record): ?string {
                        $fee = $record->fee;

                        return $fee === null ? null : $fee->minorUnits.' '.$fee->currency->value;
                    }),
                IconEntry::make('generates_credit')
                    ->label((string) __('operator_console.club.fields.generates_credit'))
                    ->boolean(),
                IconEntry::make('invite_only')
                    ->label((string) __('operator_console.club.fields.invite_only'))
                    ->boolean(),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClubs::route('/'),
            'create' => Pages\CreateClub::route('/create'),
            'view' => Pages\ViewClub::route('/{record}'),
        ];
    }

    /**
     * The Club status badge column (design D2). A Club's state lives in `status` (a `ClubStatus`), not the
     * catalog `lifecycle_state`, so the kit's `lifecycleStateColumn()` does not fit — this reads the real
     * attribute and renders it through its BackedEnum cast `->value` (e.g. `active`), avoiding any `Parties\Enums`
     * import (the {Models} read surface). `getAttribute()` is a read; the no-Eloquent-write rule polices writes.
     */
    protected static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label((string) __('operator_console.club.columns.status'))
            ->badge()
            ->getStateUsing(function (Model $record): string {
                $state = $record->getAttribute('status');

                return $state instanceof BackedEnum ? (string) $state->value : '';
            });
    }

    /**
     * The Club registration-flow classifier column. `registration_flow_type` is a `ClubRegistrationFlowType` —
     * a fixed per-Club configuration attribute (NOT a lifecycle state) — rendered through its cast `->value`
     * (e.g. `invitation_only`), import-free exactly like {@see statusColumn()}. The create surface constructs
     * this enum as an operand (Pages\CreateClub); the read column never imports it.
     */
    protected static function registrationFlowTypeColumn(): TextColumn
    {
        return TextColumn::make('registration_flow_type')
            ->label((string) __('operator_console.club.columns.registration_flow_type'))
            ->getStateUsing(function (Model $record): string {
                $state = $record->getAttribute('registration_flow_type');

                return $state instanceof BackedEnum ? (string) $state->value : '';
            });
    }
}
