<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog;

use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * ProductVariantResource — the operator console's READ-ONLY surface over the Catalog Product Variant
 * (operator-console-catalog-spine, task 3.1; design L1/L3/L4; ADR 2026-06-19 + 2026-06-20). The first of the
 * four HIERARCHICAL spine consoles, built — like Format / Case Configuration — as PURE reuse of the kit
 * extracted in tasks 1.1/1.2.
 *
 * It extends {@see OperatorConsoleResource}, which owns the read-only conventions (the
 * `operator_console.<entity>` model labels off {@see i18nKey()}, the shared `lifecycle_state` badge +
 * `version` column helpers, and the no-mutating-action default); this resource supplies only its own
 * columns/form/infolist/pages plus the one hierarchical divergence from the standalone consoles: a PARENT
 * Product Master picker on the create form (design L3). Unlike Product Master it binds NO producer — there is
 * no producer picker and no Producer-gate handling (design L6); its only parent is the within-catalog Product
 * Master, and the activation-cascade gate (Variant ← Master active) is surfaced FOR FREE by the view page's
 * wrapper (design L4), never re-checked here.
 *
 * It read-binds to {@see ProductVariant} — the ADR-sanctioned exception, OperatorPanel-only and display-only:
 * the resource queries the model (and its WITHIN-Catalog `master()` / `wineAttributes()` relations) for the
 * list table + the view infolist and NEVER writes it. Every mutation is a separate Filament Action routed
 * through a Catalog domain action (the kit's view + create pages); there is deliberately NO Edit page and NO
 * Delete/Create default action — the Catalog backend ships no update Action (post-creation field edits are out
 * of scope, proposal slice-boundary), and create lands on a write-through
 * `ProductVariantResource\Pages\CreateProductVariant` page. The no-Eloquent-write PHPStan rule (task 1.2)
 * guards the discipline. Enums are rendered through their cast instances (`->value`), never by importing
 * `App\Modules\Catalog\Enums\*`, so the console's cross-module surface stays exactly {Models, Actions} (the
 * import-boundary carve-out). All user-facing copy is localized through the `operator_console` group
 * (invariant 12).
 */
class ProductVariantResource extends OperatorConsoleResource
{
    protected static ?string $model = ProductVariant::class;

    protected static ?string $recordTitleAttribute = 'variant_identifier';

    protected static ?int $navigationSort = 2;

    protected static function i18nKey(): string
    {
        return 'product_variant';
    }

    protected static function navigationGroupCase(): OperatorConsoleNavigationGroup
    {
        return OperatorConsoleNavigationGroup::Catalog;
    }

    /**
     * The create form (design L3/L8). Collects the inputs the Catalog `CreateProductVariant` action consumes —
     * the PARENT Product Master (a within-catalog select; a Variant belongs to exactly one Master,
     * BR-Identity-2), the type-neutral variant identifier, and the WINE vintage attribute set (a vintage year,
     * the non-vintage marker, and optional translatable tasting notes). The form only COLLECTS; the write routes
     * through the action in `Pages\CreateProductVariant::createViaAction()` (there is no Edit page — the Catalog
     * backend ships no update Action). The Master select is sourced from Catalog's OWN model, read-only and
     * within-module — never a producer picker (design L6). All labels localized (invariant 12).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_master_id')
                    ->label((string) __('operator_console.product_variant.fields.product_master'))
                    ->options(self::productMasterOptions(...))
                    ->required(),
                TextInput::make('variant_identifier')
                    ->label((string) __('operator_console.product_variant.fields.variant_identifier'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('vintage_year')
                    ->label((string) __('operator_console.product_variant.fields.vintage_year'))
                    ->numeric(),
                Toggle::make('non_vintage')
                    ->label((string) __('operator_console.product_variant.fields.non_vintage'))
                    ->default(false),
                Textarea::make('tasting_notes')
                    ->label((string) __('operator_console.product_variant.fields.tasting_notes'))
                    ->helperText((string) __('operator_console.product_variant.fields.tasting_notes_help')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('variant_identifier')
                    ->label((string) __('operator_console.product_variant.columns.variant_identifier'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('master')
                    ->label((string) __('operator_console.product_variant.columns.master'))
                    ->getStateUsing(fn (ProductVariant $record): ?string => $record->master?->name),
                TextColumn::make('vintage')
                    ->label((string) __('operator_console.product_variant.columns.vintage'))
                    ->getStateUsing(fn (ProductVariant $record): string => self::vintageLabel($record)),
                static::lifecycleStateColumn(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('variant_identifier')
                    ->label((string) __('operator_console.product_variant.columns.variant_identifier')),
                TextEntry::make('master')
                    ->label((string) __('operator_console.product_variant.columns.master'))
                    ->getStateUsing(fn (ProductVariant $record): ?string => $record->master?->name),
                TextEntry::make('lifecycle_state')
                    ->label((string) __('operator_console.product_variant.columns.lifecycle_state'))
                    ->getStateUsing(fn (ProductVariant $record): string => $record->lifecycle_state->value),
                TextEntry::make('version')
                    ->label((string) __('operator_console.product_variant.columns.version')),
                TextEntry::make('vintage_year')
                    ->label((string) __('operator_console.product_variant.fields.vintage_year'))
                    ->getStateUsing(fn (ProductVariant $record): ?int => $record->wineAttributes?->vintage_year),
                TextEntry::make('non_vintage')
                    ->label((string) __('operator_console.product_variant.fields.non_vintage'))
                    ->getStateUsing(fn (ProductVariant $record): string => $record->wineAttributes?->non_vintage
                        ? (string) __('operator_console.product_variant.values.yes')
                        : (string) __('operator_console.product_variant.values.no')),
                TextEntry::make('tasting_notes')
                    ->label((string) __('operator_console.product_variant.fields.tasting_notes'))
                    ->getStateUsing(fn (ProductVariant $record): ?string => $record->wineAttributes?->tasting_notes?->resolve(app()->getLocale())),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductVariants::route('/'),
            'create' => Pages\CreateProductVariant::route('/create'),
            'view' => Pages\ViewProductVariant::route('/{record}'),
        ];
    }

    /**
     * The combined vintage display: the vintage year, or the localized "Non-vintage" marker when the Variant is
     * flagged non-vintage (the WINE variant axis lives 1:1 off the neutral core in `wineAttributes`). Reads the
     * within-Catalog attribute set; renders no `Catalog\Enums` import.
     */
    private static function vintageLabel(ProductVariant $record): string
    {
        $wineAttributes = $record->wineAttributes;

        if ($wineAttributes === null) {
            return '';
        }

        if ($wineAttributes->non_vintage) {
            return (string) __('operator_console.product_variant.values.non_vintage');
        }

        return (string) ($wineAttributes->vintage_year ?? '');
    }

    /**
     * Create-form parent-Master options, keyed by `product_master_id` → a `#id · name · state` label, read from
     * Catalog's OWN {@see ProductMaster} model (a WITHIN-module reference — a Variant's single parent, never a
     * producer, design L6). Creation lists every Master; the activation-cascade gate (a domain rule) is what
     * blocks activating a Variant under a non-active Master, so the picker need not pre-filter by state. The
     * Master's lifecycle state is rendered through its cast instance (`->value`), so no `Catalog\Enums` import is
     * needed (the {Models, Actions} surface).
     *
     * @return array<int, string>
     */
    private static function productMasterOptions(): array
    {
        return ProductMaster::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(static fn (ProductMaster $master): array => [
                $master->id => '#'.$master->id.' · '.$master->name.' · '.$master->lifecycle_state->value,
            ])
            ->all();
    }
}
