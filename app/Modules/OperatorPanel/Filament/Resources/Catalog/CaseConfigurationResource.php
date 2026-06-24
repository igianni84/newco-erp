<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog;

use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\OperatorPanel\Filament\Clusters\CatalogSettings;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CaseConfigurationResource\Pages;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * CaseConfigurationResource — the operator console's READ-ONLY surface over the Catalog Case Configuration
 * (operator-console-catalog-spine, task 2.2; design L1/L3; ADR 2026-06-19 + 2026-06-20). The second of the six
 * spine consoles, built — like Format — as PURE reuse of the kit extracted in tasks 1.1/1.2.
 *
 * It extends {@see OperatorConsoleResource}, which owns the read-only conventions (the
 * `operator_console.<entity>` model labels off {@see i18nKey()}, the shared `lifecycle_state` badge +
 * `version` column helpers, and the no-mutating-action default); this resource supplies only its own
 * columns/form/infolist/pages. A Case Configuration is a STANDALONE reference entity — no parent, no producer —
 * so there is no parent picker and no Master-only producer picker (design L6). It carries NO breakability
 * attribute (BR-RefData-2): whether a case may be split is decided downstream in Module A/S, never captured
 * here — so the create form exposes none, exactly mirroring the {@see CaseConfiguration} table.
 *
 * It read-binds to {@see CaseConfiguration} — the ADR-sanctioned exception, OperatorPanel-only and display-only:
 * the resource queries the model for the list table + the view infolist and NEVER writes it. Every mutation is
 * a separate Filament Action routed through a Catalog domain action (the kit's view + create pages); there is
 * deliberately NO Edit page and NO Delete/Create default action — the Catalog backend ships no update Action
 * (post-creation field edits are out of scope, proposal slice-boundary), and create lands on a write-through
 * `CaseConfigurationResource\Pages\CreateCaseConfiguration` page. The no-Eloquent-write PHPStan rule (task 1.2)
 * guards the discipline. The `lifecycle_state` enum is rendered through its cast instance (`->value`), never by
 * importing `App\Modules\Catalog\Enums\*`, so the console's cross-module surface stays exactly {Models, Actions}
 * (the import-boundary carve-out). All user-facing copy is localized through the `operator_console` group
 * (invariant 12).
 */
class CaseConfigurationResource extends OperatorConsoleResource
{
    protected static ?string $model = CaseConfiguration::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 7;

    // Grouped into the Catalog "Settings" cluster (operator-console UI pass, 2026-06-24): Case Configuration is
    // reference data, surfaced as a tab under Settings rather than a flat top-level Catalog console.
    protected static ?string $cluster = CatalogSettings::class;

    protected static function i18nKey(): string
    {
        return 'case_configuration';
    }

    protected static function navigationGroupCase(): OperatorConsoleNavigationGroup
    {
        return OperatorConsoleNavigationGroup::Catalog;
    }

    /**
     * Clustered into {@see CatalogSettings}: the cluster carries the sidebar group, so the resource reports NO
     * navigation group — keeping the cluster's sub-navigation a flat tab strip. navigationGroupCase() stays
     * declared (the kit base requires it) but is unused for placement once $cluster is set.
     */
    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return null;
    }

    /**
     * The create form (design L3/L8). Collects the scalar inputs the Catalog `CreateCaseConfiguration` action
     * consumes — a name, the units per case, and a packaging type. The form only COLLECTS; the write routes
     * through the action in `Pages\CreateCaseConfiguration::createViaAction()` (there is no Edit page — the
     * Catalog backend ships no update Action). There is deliberately NO breakability input (BR-RefData-2). All
     * labels localized (invariant 12).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label((string) __('operator_console.case_configuration.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('units_per_case')
                    ->label((string) __('operator_console.case_configuration.fields.units_per_case'))
                    ->required()
                    ->numeric()
                    ->minValue(1),
                TextInput::make('packaging_type')
                    ->label((string) __('operator_console.case_configuration.fields.packaging_type'))
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyConsoleDefaults($table)
            ->columns([
                TextColumn::make('name')
                    ->label((string) __('operator_console.case_configuration.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('units_per_case')
                    ->label((string) __('operator_console.case_configuration.columns.units_per_case'))
                    ->numeric()
                    ->sortable(),
                // `packaging_type` is a plain string column (no enum cast — the migration declares a `string`),
                // so it renders directly; it is filterable via the distinct-token state filter.
                TextColumn::make('packaging_type')
                    ->label((string) __('operator_console.case_configuration.columns.packaging_type'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                static::lifecycleStateColumn(),
            ])
            ->filters([
                static::stateFilter(),
                static::stateFilter('packaging_type', 'columns.packaging_type'),
            ]);
    }

    /**
     * Make the Case Configuration findable from the Cmd/Ctrl+K global search by its name (invariant 12: the
     * label resolves through {@see getModelLabel()}). Pairs with {@see $recordTitleAttribute} = 'name'.
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    /**
     * The read-only view (design L1/L4). Grouped into premium, icon-headed sections — Identity (the human name),
     * Packaging (units per case + packaging type), and State (the `lifecycle_state` rendered as the same semantic
     * colored + iconed badge the list carries, via {@see badgedStateEntry()}), closing with the collapsed Metadata
     * section for the optimistic-lock `version`. Every entry is display-only; the model is never written here (the
     * no-Eloquent-write rule, task 1.2). All copy localized (invariant 12).
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make((string) __('operator_console.case_configuration.sections.identity'))
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label((string) __('operator_console.case_configuration.columns.name'))
                            ->weight('bold'),
                    ]),
                Section::make((string) __('operator_console.case_configuration.sections.packaging'))
                    ->icon('heroicon-o-cube')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('units_per_case')
                            ->label((string) __('operator_console.case_configuration.columns.units_per_case'))
                            ->numeric(),
                        TextEntry::make('packaging_type')
                            ->label((string) __('operator_console.case_configuration.columns.packaging_type'))
                            ->badge()
                            ->color('gray'),
                    ]),
                Section::make((string) __('operator_console.case_configuration.sections.state'))
                    ->icon('heroicon-o-check-badge')
                    ->columns(2)
                    ->schema([
                        static::badgedStateEntry(),
                    ]),
                static::metadataSection(),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCaseConfigurations::route('/'),
            'create' => Pages\CreateCaseConfiguration::route('/create'),
            'view' => Pages\ViewCaseConfiguration::route('/{record}'),
        ];
    }
}
