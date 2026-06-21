<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource\Pages;
use App\Modules\Parties\Models\ProducerAgreement;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ProducerAgreementResource — the operator console's READ-ONLY surface over the Parties ProducerAgreement
 * (operator-console-parties-supply-side, task 7.1; design D2/D6/D7; ADR 2026-06-19 + 2026-06-20 + 2026-06-21).
 * The THIRD console of the Parties (Module K) supply-side, built — like {@see ProducerResource} and
 * {@see ClubResource} — as the non-catalog trait-reuse pattern (the View page assembles its own verb set; see
 * `ProducerAgreementResource\Pages\ViewProducerAgreement`).
 *
 * It extends {@see OperatorConsoleResource} for the read-only conventions every console shares — the
 * `operator_console.<entity>` model labels off {@see i18nKey()} and the optimistic-lock `version` column helper.
 * It does NOT call the kit's `lifecycleStateColumn()`: a ProducerAgreement's state attribute is `status` (a
 * `ProducerAgreementStatus`, `draft → active → superseded | terminated`), not the catalog `lifecycle_state` — so
 * this resource supplies its OWN `status` badge column (design D2), rendering the state enum through its cast
 * `->value` and never importing `App\Modules\Parties\Enums\ProducerAgreementStatus` (the {Models} surface for the
 * read path).
 *
 * It read-binds to {@see ProducerAgreement} — the ADR-sanctioned exception, OperatorPanel-only and display-only:
 * the resource queries the model (and its two WITHIN-Parties relations — the required `producer()` and the
 * OPTIONAL `club()` narrowing) for the list table + the view infolist and NEVER writes it. A null `club_id` is a
 * Producer-wide agreement (§ 4.6); {@see clubLabel()} renders the `producer_wide` placeholder for it. Every
 * mutation is a separate Filament Action routed through a Parties domain action (the kit's create page + the
 * bespoke view page); there is deliberately NO Edit page and NO Delete/Create default action — the Parties
 * backend ships no agreement update Action, and create lands on a write-through {@see Pages\CreateProducerAgreement}
 * page that takes ids/dates/a free string only (NO operand enum — design D7). The no-Eloquent-write PHPStan rule
 * guards the discipline. All user-facing copy is localized through the `operator_console` group (invariant 12).
 */
class ProducerAgreementResource extends OperatorConsoleResource
{
    protected static ?string $model = ProducerAgreement::class;

    protected static function i18nKey(): string
    {
        return 'producer_agreement';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('producer')
                    ->label((string) __('operator_console.producer_agreement.columns.producer'))
                    ->getStateUsing(fn (ProducerAgreement $record): string => $record->producer->name),
                TextColumn::make('club')
                    ->label((string) __('operator_console.producer_agreement.columns.club'))
                    ->getStateUsing(fn (ProducerAgreement $record): string => self::clubLabel($record)),
                static::statusColumn(),
                TextColumn::make('term_start')
                    ->label((string) __('operator_console.producer_agreement.columns.term_start')),
                TextColumn::make('term_end')
                    ->label((string) __('operator_console.producer_agreement.columns.term_end')),
                static::versionColumn(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('producer')
                    ->label((string) __('operator_console.producer_agreement.columns.producer'))
                    ->getStateUsing(fn (ProducerAgreement $record): string => $record->producer->name),
                TextEntry::make('club')
                    ->label((string) __('operator_console.producer_agreement.columns.club'))
                    ->getStateUsing(fn (ProducerAgreement $record): string => self::clubLabel($record)),
                TextEntry::make('status')
                    ->label((string) __('operator_console.producer_agreement.columns.status'))
                    ->getStateUsing(fn (Model $record): string => self::statusValue($record)),
                TextEntry::make('term_start')
                    ->label((string) __('operator_console.producer_agreement.columns.term_start')),
                TextEntry::make('term_end')
                    ->label((string) __('operator_console.producer_agreement.columns.term_end')),
                TextEntry::make('settlement_cadence')
                    ->label((string) __('operator_console.producer_agreement.fields.settlement_cadence')),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducerAgreements::route('/'),
            'create' => Pages\CreateProducerAgreement::route('/create'),
            'view' => Pages\ViewProducerAgreement::route('/{record}'),
        ];
    }

    /**
     * The ProducerAgreement status badge column (design D2). The state lives in `status` (a
     * `ProducerAgreementStatus`), not the catalog `lifecycle_state`, so the kit's `lifecycleStateColumn()` does
     * not fit — this reads the real attribute and renders it through its BackedEnum cast `->value` (e.g. `draft`),
     * avoiding any `Parties\Enums` import (the {Models} read surface). `getAttribute()` is a read; the
     * no-Eloquent-write rule polices writes.
     */
    protected static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label((string) __('operator_console.producer_agreement.columns.status'))
            ->badge()
            ->getStateUsing(fn (Model $record): string => self::statusValue($record));
    }

    /**
     * Render the `status` state enum through its cast `->value` — the import-free read surface shared by the badge
     * column and the infolist entry (the {Models} surface, never a `Parties\Enums` import).
     */
    private static function statusValue(Model $record): string
    {
        $state = $record->getAttribute('status');

        return $state instanceof BackedEnum ? (string) $state->value : '';
    }

    /**
     * The scoped Club's display name, or the localized `producer_wide` placeholder when the agreement is
     * Producer-wide (a null `club` — § 4.6). `club` is the OPTIONAL within-Parties `belongsTo`
     * ({@see ProducerAgreement::club()}); the local + null-check narrows the `Club|null` to `Club` before the
     * `display_name` read (the nullsafe `?->` form is rejected because Larastan types the nullsafe operand off
     * the non-null relation generic). Read-only — the no-Eloquent-write rule polices writes.
     */
    private static function clubLabel(ProducerAgreement $record): string
    {
        $club = $record->club;

        return $club === null
            ? (string) __('operator_console.producer_agreement.producer_wide')
            : $club->display_name;
    }
}
