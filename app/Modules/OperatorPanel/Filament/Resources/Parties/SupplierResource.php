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
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * SupplierResource — the operator console's READ surface over the Parties Supplier (operator-console UI pass,
 * 2026-06-24). Supplier is the deliberately-thin commercial-counterpart Party subtype (§ 4.5): a legal name +
 * the immutable `supplier` party-type marker, with NO lifecycle and NO version. So unlike every other Parties
 * console it carries NO status/lifecycle badge (the kit's `lifecycleStateColumn()` does not apply) and its view
 * page is a plain read (no lifecycle verbs).
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
        return $table
            ->columns([
                TextColumn::make('legal_name')
                    ->label((string) __('operator_console.supplier.columns.legal_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('party_type')
                    ->label((string) __('operator_console.supplier.columns.party_type'))
                    ->badge()
                    ->getStateUsing(fn (Supplier $record): string => $record->party_type->value),
                TextColumn::make('created_at')
                    ->label((string) __('operator_console.supplier.columns.created_at'))
                    ->dateTime(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('legal_name')
                    ->label((string) __('operator_console.supplier.columns.legal_name')),
                TextEntry::make('party_type')
                    ->label((string) __('operator_console.supplier.columns.party_type'))
                    ->badge()
                    ->getStateUsing(fn (Supplier $record): string => $record->party_type->value),
                TextEntry::make('created_at')
                    ->label((string) __('operator_console.supplier.columns.created_at'))
                    ->dateTime(),
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
