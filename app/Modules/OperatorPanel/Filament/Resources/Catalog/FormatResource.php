<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog;

use App\Modules\Catalog\Models\Format;
use App\Modules\OperatorPanel\Filament\Clusters\CatalogSettings;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\FormatResource\Pages;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * FormatResource — the operator console's READ-ONLY surface over the Catalog Format
 * (operator-console-catalog-spine, task 2.1; design L1/L3; ADR 2026-06-19 + 2026-06-20). It is the first of
 * the six spine consoles built as PURE reuse of the kit extracted in tasks 1.1/1.2.
 *
 * It extends {@see OperatorConsoleResource}, which owns the read-only conventions (the
 * `operator_console.<entity>` model labels off {@see i18nKey()}, the shared `lifecycle_state` badge +
 * `version` column helpers, and the no-mutating-action default); this resource supplies only its own
 * columns/form/infolist/pages. Format is a STANDALONE reference entity — no parent, no producer — so there is
 * no parent picker and no Master-only producer picker (design L6).
 *
 * It read-binds to {@see Format} — the ADR-sanctioned exception, OperatorPanel-only and display-only: the
 * resource queries the model for the list table + the view infolist and NEVER writes it. Every mutation is a
 * separate Filament Action routed through a Catalog domain action (the kit's view + create pages); there is
 * deliberately NO Edit page and NO Delete/Create default action — the Catalog backend ships no update Action
 * (post-creation field edits are out of scope, proposal slice-boundary), and create lands on a write-through
 * {@see Pages\CreateFormat} page. The no-Eloquent-write PHPStan rule (task 1.2) guards the discipline. The
 * `lifecycle_state` enum is rendered through its cast instance (`->value`), never by importing
 * `App\Modules\Catalog\Enums\*`, so the console's cross-module surface stays exactly {Models, Actions} (the
 * import-boundary carve-out). All user-facing copy is localized through the `operator_console` group
 * (invariant 12).
 */
class FormatResource extends OperatorConsoleResource
{
    protected static ?string $model = Format::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    // Grouped into the Catalog "Settings" cluster (operator-console UI pass, 2026-06-24): Format is reference
    // data, surfaced as a tab under Settings rather than a flat top-level Catalog console.
    protected static ?string $cluster = CatalogSettings::class;

    protected static function i18nKey(): string
    {
        return 'format';
    }

    protected static function navigationGroupCase(): OperatorConsoleNavigationGroup
    {
        return OperatorConsoleNavigationGroup::Catalog;
    }

    /**
     * Clustered into {@see CatalogSettings}: the cluster (not this resource) carries the sidebar group, so the
     * resource reports NO navigation group — keeping the cluster's sub-navigation a flat tab strip rather than
     * re-nesting a "Catalog" sub-heading. navigationGroupCase() stays declared (the kit base requires it) but is
     * unused for placement once $cluster is set.
     */
    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return null;
    }

    /**
     * The create form (design L3/L8). Collects the scalar inputs the Catalog `CreateFormat` action consumes —
     * a name, a size label, and a volume in millilitres. The form only COLLECTS; the write routes through the
     * action in {@see Pages\CreateFormat::createViaAction()} (there is no Edit page — the Catalog backend ships
     * no update Action). All labels localized (invariant 12).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label((string) __('operator_console.format.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('size_label')
                    ->label((string) __('operator_console.format.fields.size_label'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('volume_ml')
                    ->label((string) __('operator_console.format.fields.volume_ml'))
                    ->required()
                    ->numeric()
                    ->minValue(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyConsoleDefaults($table)
            ->columns([
                TextColumn::make('name')
                    ->label((string) __('operator_console.format.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('size_label')
                    ->label((string) __('operator_console.format.columns.size_label'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('volume_ml')
                    ->label((string) __('operator_console.format.columns.volume_ml'))
                    ->sortable(),
                static::lifecycleStateColumn(),
            ])
            ->filters([
                static::stateFilter(),
            ]);
    }

    /**
     * Make the Format findable from the Cmd/Ctrl+K global search by its human identity — the size NAME
     * (e.g. "Magnum") and its size LABEL — never the bare id (invariant 12: the label resolves through
     * {@see getModelLabel()}). Pairs with {@see $recordTitleAttribute} = 'name'.
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'size_label'];
    }

    /**
     * The read-only view (design L10). Grouped into premium, icon-headed sections — Identity (the size name,
     * its label and the volume in millilitres) and State (the `lifecycle_state` rendered as the same semantic
     * colored + iconed badge the list carries, via {@see badgedStateEntry()}) — and closed with the shared
     * collapsed Metadata section for the optimistic-lock `version`. Every entry is display-only; all copy
     * localized (invariant 12).
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make((string) __('operator_console.format.sections.identity'))
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label((string) __('operator_console.format.columns.name'))
                            ->weight('bold'),
                        TextEntry::make('size_label')
                            ->label((string) __('operator_console.format.columns.size_label')),
                        TextEntry::make('volume_ml')
                            ->label((string) __('operator_console.format.columns.volume_ml')),
                    ]),
                Section::make((string) __('operator_console.format.sections.state'))
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
            'index' => Pages\ListFormats::route('/'),
            'create' => Pages\CreateFormat::route('/create'),
            'view' => Pages\ViewFormat::route('/{record}'),
        ];
    }
}
