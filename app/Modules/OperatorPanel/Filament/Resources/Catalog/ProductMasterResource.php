<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog;

use App\Modules\Catalog\Models\ProducerState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * ProductMasterResource — the operator console's READ-ONLY surface over the Catalog Product Master
 * (operator-console-catalog-master, task 2.1; design L1/L10; ADR 2026-06-19).
 *
 * It read-binds to {@see ProductMaster} — the ADR-sanctioned exception, OperatorPanel-only and
 * display-only: the resource queries the model for the list table + the view infolist and NEVER writes it.
 * Every mutation is a separate Filament Action routed through a Catalog domain action (tasks 3–5); there is
 * deliberately NO Edit page and NO Delete/Create default action here — the Catalog backend ships no update
 * Action, so post-creation field edits are out of scope (proposal slice-boundary), and create lands as its
 * own write-through page in task 3.1. The no-Eloquent-write PHPStan rule (task 1.2) guards the discipline.
 *
 * The producer column resolves status through Catalog's OWN producer-state projection
 * ({@see ProducerState}, `catalog_producer_states`), never Module K (invariant 10). Enums are rendered
 * through their cast instances (`$record->lifecycle_state->value`) and the projection status through the
 * raw column value — never by importing `App\Modules\Catalog\Enums\*`, so the console's cross-module surface
 * stays exactly {Models, Actions} (the import-boundary carve-out, task 1.3). All user-facing copy is
 * localized through the `operator_console` group (invariant 12).
 */
class ProductMasterResource extends Resource
{
    protected static ?string $model = ProductMaster::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return (string) __('operator_console.product_master.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('operator_console.product_master.plural_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label((string) __('operator_console.product_master.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product_type')
                    ->label((string) __('operator_console.product_master.columns.product_type'))
                    ->getStateUsing(fn (ProductMaster $record): string => $record->product_type->value),
                TextColumn::make('lifecycle_state')
                    ->label((string) __('operator_console.product_master.columns.lifecycle_state'))
                    ->badge()
                    ->getStateUsing(fn (ProductMaster $record): string => $record->lifecycle_state->value),
                TextColumn::make('producer')
                    ->label((string) __('operator_console.product_master.columns.producer'))
                    ->getStateUsing(fn (ProductMaster $record): string => self::producerLabel($record)),
                TextColumn::make('version')
                    ->label((string) __('operator_console.product_master.columns.version')),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label((string) __('operator_console.product_master.columns.name')),
                TextEntry::make('product_type')
                    ->label((string) __('operator_console.product_master.columns.product_type'))
                    ->getStateUsing(fn (ProductMaster $record): string => $record->product_type->value),
                TextEntry::make('lifecycle_state')
                    ->label((string) __('operator_console.product_master.columns.lifecycle_state'))
                    ->getStateUsing(fn (ProductMaster $record): string => $record->lifecycle_state->value),
                TextEntry::make('producer')
                    ->label((string) __('operator_console.product_master.columns.producer'))
                    ->getStateUsing(fn (ProductMaster $record): string => self::producerLabel($record)),
                TextEntry::make('version')
                    ->label((string) __('operator_console.product_master.columns.version')),
                TextEntry::make('appellation')
                    ->label((string) __('operator_console.product_master.fields.appellation'))
                    ->getStateUsing(fn (ProductMaster $record): ?string => $record->wineAttributes?->appellation),
                TextEntry::make('region')
                    ->label((string) __('operator_console.product_master.fields.region'))
                    ->getStateUsing(fn (ProductMaster $record): ?string => $record->wineAttributes?->region),
                TextEntry::make('winery_story')
                    ->label((string) __('operator_console.product_master.fields.winery_story'))
                    ->getStateUsing(fn (ProductMaster $record): ?string => $record->wineAttributes?->winery_story?->resolve(app()->getLocale())),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductMasters::route('/'),
            'view' => Pages\ViewProductMaster::route('/{record}'),
        ];
    }

    /**
     * The producer display label: the plain producer id plus its gate-relevant status read from Catalog's
     * OWN producer-state projection, or a localized "not projected" marker when no projection row exists yet
     * (a producer is projected only once Parties emits ProducerActivated/Retired — design D3). Read-only and
     * Catalog-local; never Module K (invariant 10). The raw `status` value is used directly (the projection
     * exposes no human name), avoiding any `Catalog\Enums` import.
     */
    private static function producerLabel(ProductMaster $record): string
    {
        $producerState = ProducerState::query()
            ->where('producer_id', $record->producer_id)
            ->first();

        // Render the projection status through its cast instance (consistent with lifecycle_state /
        // product_type), so no `Catalog\Enums` import is needed. NB Eloquent's Builder::value() would
        // hydrate + cast (returning the enum, not a raw string) — hence first()->status->value here.
        $statusLabel = $producerState?->status->value
            ?? (string) __('operator_console.product_master.producer_unprojected');

        return '#'.$record->producer_id.' · '.$statusLabel;
    }
}
