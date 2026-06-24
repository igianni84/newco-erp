<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\SupplierResource\Pages;
use App\Modules\Parties\Actions\CreateSupplier;
use App\Modules\Parties\Models\Supplier;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * SupplierResource — the operator console's READ surface over the Parties Supplier (operator-console UI pass,
 * 2026-06-24). Supplier is the deliberately-thin commercial-counterpart Party subtype (§ 4.5): a legal name +
 * the immutable `supplier` party-type marker, with NO lifecycle and NO version. So unlike every other Parties
 * console it carries NO status/lifecycle badge (the kit's `lifecycleStateColumn()` does not apply, and the
 * infolist uses no `badgedStateEntry()`) and its view page is a plain read (no lifecycle verbs).
 *
 * The list mirrors the premium template — branded `applyConsoleDefaults()` (newest-first + localized empty
 * state), the searchable/sortable `legal_name` identity column, the `party_type` marker as a badge, a
 * `party_type` `stateFilter()`, and `getGloballySearchableAttributes()` for Cmd/Ctrl+K. The infolist groups
 * into icon-headed Sections; because Supplier has no `version`, the collapsed `metadataSection()` is fed
 * EXPLICIT created/updated timestamp entries rather than the kit's optimistic-lock default.
 *
 * It read-binds to {@see Supplier} (the ADR-sanctioned OperatorPanel-only display exception); create routes
 * through the Parties {@see CreateSupplier} action via the write-through
 * {@see Pages\CreateSupplier} page — the console never writes the model (the no-Eloquent-write rule, ADR
 * 2026-06-19). `party_type` renders through its cast `->value`, no `Parties\Enums` import. All copy is localized
 * (invariant 12).
 */
class SupplierResource extends OperatorConsoleResource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $recordTitleAttribute = 'legal_name';

    protected static ?int $navigationSort = 6;

    protected static function i18nKey(): string
    {
        return 'supplier';
    }

    protected static function navigationGroupCase(): OperatorConsoleNavigationGroup
    {
        return OperatorConsoleNavigationGroup::Parties;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('legal_name')
                    ->label((string) __('operator_console.supplier.fields.legal_name'))
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyConsoleDefaults($table)
            ->columns([
                TextColumn::make('legal_name')
                    ->label((string) __('operator_console.supplier.columns.legal_name'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('party_type')
                    ->label((string) __('operator_console.supplier.columns.party_type'))
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->getStateUsing(fn (Supplier $record): string => $record->party_type->value),
                TextColumn::make('created_at')
                    ->label((string) __('operator_console.supplier.columns.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                static::stateFilter('party_type', 'columns.party_type'),
            ]);
    }

    /**
     * Make the Supplier findable from the Cmd/Ctrl+K global search by its legal name (invariant 12: the label
     * resolves through {@see getModelLabel()}). Pairs with {@see $recordTitleAttribute} = 'legal_name'.
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['legal_name'];
    }

    /**
     * The read-only view (operator-console UI pass, 2026-06-24). Grouped into premium, icon-headed sections
     * mirroring the catalog/Parties template — Identity (the supplier's legal name + the immutable `party_type`
     * marker rendered as a badge), and a collapsed Metadata section. Supplier is the deliberately-thin Party
     * subtype (§ 4.5): it carries NO `status`/`lifecycle_state` (so no `badgedStateEntry()`) and NO `version`
     * column (so Metadata holds the created/updated timestamps, NOT the kit's optimistic-lock default). Every
     * entry is display-only; `party_type` renders through its cast `->value`, no `Parties\Enums` import. All
     * copy localized (invariant 12).
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make((string) __('operator_console.supplier.sections.identity'))
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('legal_name')
                            ->label((string) __('operator_console.supplier.columns.legal_name'))
                            ->weight('bold'),
                        TextEntry::make('party_type')
                            ->label((string) __('operator_console.supplier.columns.party_type'))
                            ->badge()
                            ->color('info')
                            ->getStateUsing(fn (Supplier $record): string => $record->party_type->value),
                    ]),
                static::metadataSection([
                    TextEntry::make('created_at')
                        ->label((string) __('operator_console.supplier.columns.created_at'))
                        ->dateTime(),
                    TextEntry::make('updated_at')
                        ->label((string) __('operator_console.supplier.columns.updated_at'))
                        ->dateTime(),
                ]),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'view' => Pages\ViewSupplier::route('/{record}'),
        ];
    }
}
