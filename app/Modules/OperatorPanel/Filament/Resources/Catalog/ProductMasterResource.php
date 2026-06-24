<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog;

use App\Modules\Catalog\Models\ProducerState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\RelationManagers\VariantsRelationManager;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * ProductMasterResource — the operator console's READ-ONLY surface over the Catalog Product Master
 * (operator-console-catalog-master, task 2.1; retrofitted onto the shared {@see OperatorConsoleResource} kit in
 * operator-console-catalog-spine, task 1.2; design L1/L2/L10; ADR 2026-06-19 + 2026-06-20).
 *
 * It now extends the kit base, which owns the read-only conventions (the `operator_console.<entity>` model
 * labels off {@see i18nKey()}, the shared `lifecycle_state` badge + `version` column helpers, and the
 * no-mutating-action default); this resource supplies only its own columns/form/infolist/pages and the
 * Master-only producer picker (design L6).
 *
 * It read-binds to {@see ProductMaster} — the ADR-sanctioned exception, OperatorPanel-only and
 * display-only: the resource queries the model for the list table + the view infolist and NEVER writes it.
 * Every mutation is a separate Filament Action routed through a Catalog domain action (tasks 3–5); there is
 * deliberately NO Edit page and NO Delete/Create default action here — the Catalog backend ships no update
 * Action, so post-creation field edits are out of scope (proposal slice-boundary), and create lands as its
 * own write-through page in task 3.1. The no-Eloquent-write PHPStan rule (task 1.2) guards the discipline.
 *
 * The producer column resolves the producer's display NAME, denormalized onto Catalog's OWN producer-state
 * projection ({@see ProducerState}, `catalog_producer_states`), never Module K (invariant 10). The
 * `lifecycle_state` enum is rendered through its cast instance (`$record->lifecycle_state->value`) — never by
 * importing `App\Modules\Catalog\Enums\*`, so the console's cross-module surface stays exactly {Models,
 * Actions} (the import-boundary carve-out, task 1.3). All user-facing copy is localized through the
 * `operator_console` group (invariant 12).
 */
class ProductMasterResource extends OperatorConsoleResource
{
    protected static ?string $model = ProductMaster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    protected static function i18nKey(): string
    {
        return 'product_master';
    }

    protected static function navigationGroupCase(): OperatorConsoleNavigationGroup
    {
        return OperatorConsoleNavigationGroup::Catalog;
    }

    /**
     * The create form (task 3.1; design L2/L6/L8). Collects the manual-baseline inputs the Catalog
     * `CreateProductMaster` action consumes — name, producer, the WINE identity attributes
     * (appellation/region) and an optional winery story. The producer is a select sourced from
     * Catalog's OWN producer-state projection ({@see ProducerState}), read-only and Catalog-local, never
     * Module K (invariant 10). The form only COLLECTS; the write routes through the action in
     * {@see Pages\CreateProductMaster::handleRecordCreation()} (there is no Edit page —
     * the Catalog backend ships no update Action). All labels localized (invariant 12).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label((string) __('operator_console.product_master.fields.name'))
                    ->required()
                    ->maxLength(255),
                Select::make('producer_id')
                    ->label((string) __('operator_console.product_master.fields.producer'))
                    ->options(self::producerOptions(...))
                    ->required(),
                TextInput::make('appellation')
                    ->label((string) __('operator_console.product_master.fields.appellation'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('region')
                    ->label((string) __('operator_console.product_master.fields.region'))
                    ->required()
                    ->maxLength(255),
                Textarea::make('winery_story')
                    ->label((string) __('operator_console.product_master.fields.winery_story'))
                    ->helperText((string) __('operator_console.product_master.fields.winery_story_help')),
            ]);
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
                static::lifecycleStateColumn(),
                TextColumn::make('producer')
                    ->label((string) __('operator_console.product_master.columns.producer'))
                    ->getStateUsing(fn (ProductMaster $record): string => self::producerLabel($record)),            ]);
    }

    /**
     * The read-only view (design L10). Grouped into premium, icon-headed sections — Identity, Classification &
     * State (the `lifecycle_state` rendered as the same semantic colored badge the list carries), Provenance &
     * Story, the child Product Variants (the within-Catalog {@see ProductMaster::variants()} relation, surfaced
     * so the operator sees a Master's releases at a glance), and a collapsed Metadata section for the
     * optimistic-lock `version`. Every entry is display-only; the producer NAME resolves through
     * {@see producerLabel()} (the Catalog projection, never Module K — invariant 10). All copy localized
     * (invariant 12).
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make((string) __('operator_console.product_master.sections.identity'))
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label((string) __('operator_console.product_master.columns.name'))
                            ->weight('bold'),
                        TextEntry::make('product_type')
                            ->label((string) __('operator_console.product_master.columns.product_type'))
                            ->badge()
                            ->color('primary')
                            ->getStateUsing(fn (ProductMaster $record): string => $record->product_type->value),
                    ]),
                Section::make((string) __('operator_console.product_master.sections.classification'))
                    ->icon('heroicon-o-check-badge')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('lifecycle_state')
                            ->label((string) __('operator_console.product_master.columns.lifecycle_state'))
                            ->badge()
                            ->color(fn (string $state): string => static::stateBadgeColor($state))
                            ->icon(fn (string $state): ?string => static::stateBadgeIcon($state))
                            ->getStateUsing(fn (ProductMaster $record): string => $record->lifecycle_state->value),
                        TextEntry::make('producer')
                            ->label((string) __('operator_console.product_master.columns.producer'))
                            ->getStateUsing(fn (ProductMaster $record): string => self::producerLabel($record)),
                    ]),
                Section::make((string) __('operator_console.product_master.sections.provenance'))
                    ->icon('heroicon-o-map-pin')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('appellation')
                            ->label((string) __('operator_console.product_master.fields.appellation'))
                            ->getStateUsing(fn (ProductMaster $record): ?string => $record->wineAttributes?->appellation),
                        TextEntry::make('region')
                            ->label((string) __('operator_console.product_master.fields.region'))
                            ->getStateUsing(fn (ProductMaster $record): ?string => $record->wineAttributes?->region),
                        TextEntry::make('winery_story')
                            ->label((string) __('operator_console.product_master.fields.winery_story'))
                            ->columnSpanFull()
                            ->getStateUsing(fn (ProductMaster $record): ?string => $record->wineAttributes?->winery_story?->resolve(app()->getLocale())),
                    ]),
                Section::make((string) __('operator_console.product_master.sections.metadata'))
                    ->icon('heroicon-o-clock')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('version')
                            ->label((string) __('operator_console.product_master.columns.version')),
                    ]),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductMasters::route('/'),
            'create' => Pages\CreateProductMaster::route('/create'),
            'view' => Pages\ViewProductMaster::route('/{record}'),
        ];
    }

    /**
     * The Product Master's child Product Variants, surfaced as an interactive sub-table on the view page (the
     * standalone Variant console is hidden from the sidebar — operator-console UI pass, 2026-06-24). The operator
     * sees and creates a Master's Variants in the Master's own context, the parent implied. ViewRecord renders
     * relation managers below the infolist, so this replaces the former static "Variants" RepeatableEntry section.
     *
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            VariantsRelationManager::class,
        ];
    }

    /**
     * The producer DISPLAY label: the producer's human NAME, denormalized onto Catalog's OWN producer-state
     * projection ({@see ProducerState::$producer_name}), or a localized "not projected" marker when no
     * projection row exists yet (a producer is projected only once Parties emits ProducerActivated/Retired —
     * design D3). When a row exists but carries no name yet (the event-driven runtime path until the producer
     * events carry the name — see the projection migration), it falls back to the bare id. Read-only and
     * Catalog-local; never Module K (invariant 10), and no `Catalog\Enums` import.
     */
    private static function producerLabel(ProductMaster $record): string
    {
        $producerState = ProducerState::query()
            ->where('producer_id', $record->producer_id)
            ->first();

        if ($producerState === null) {
            return (string) __('operator_console.product_master.producer_unprojected');
        }

        return $producerState->producer_name ?? '#'.$record->producer_id;
    }

    /**
     * Create-form producer options, keyed by `producer_id` → the producer's display NAME (or the bare id when
     * the projection row carries no name yet), read from Catalog's OWN producer-state projection
     * ({@see ProducerState}). A producer is selectable only once it has been projected (Parties emits
     * ProducerActivated/Retired — design D3); the Producer-activation gate (a domain rule) is what blocks
     * activating a Master under a non-active producer, so creation lists every projected producer. Read-only
     * and Catalog-local; never Module K (invariant 10), and no `Catalog\Enums` import (the {Models, Actions}
     * surface, task 1.3).
     *
     * @return array<int, string>
     */
    private static function producerOptions(): array
    {
        return ProducerState::query()
            ->orderBy('producer_id')
            ->get()
            ->mapWithKeys(static fn (ProducerState $state): array => [
                $state->producer_id => $state->producer_name ?? '#'.$state->producer_id,
            ])
            ->all();
    }
}
