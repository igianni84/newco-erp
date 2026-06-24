<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog;

use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\OperatorPanel\Filament\Clusters\CatalogSettings;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ProductReferenceResource — the operator console's READ-ONLY surface over the Catalog Product Reference
 * (operator-console-catalog-spine, task 3.2; design L1/L3/L4/L5; ADR 2026-06-19 + 2026-06-20). The second
 * HIERARCHICAL spine console, built — like Product Variant — as PURE reuse of the kit extracted in tasks
 * 1.1/1.2, with the one structural step up from the Variant: a Product Reference is the atomic product key
 * composed of EXACTLY TWO within-catalog parents (a Product Variant + a Format, BR-Identity-3), so its create
 * form carries TWO parent pickers, and its activation-cascade gate depends on BOTH parents being `active`.
 *
 * It extends {@see OperatorConsoleResource}, which owns the read-only conventions (the
 * `operator_console.<entity>` model labels off {@see i18nKey()}, the shared `lifecycle_state` badge +
 * `version` column helpers, and the no-mutating-action default); this resource supplies only its own
 * columns/form/infolist/pages plus the hierarchical divergences from the standalone consoles: the two parent
 * pickers (Product Variant + Format) on the create form (design L3). Unlike Product Master it binds NO producer
 * — there is no producer picker and no Producer-gate handling (design L6); its only parents are the
 * within-catalog Variant and Format, and the activation-cascade gate (PR ← Variant AND Format active) is
 * surfaced FOR FREE by the view page's wrapper (design L4), never re-checked here.
 *
 * It read-binds to {@see ProductReference} — the ADR-sanctioned exception, OperatorPanel-only and display-only:
 * the resource queries the model (and its WITHIN-Catalog `variant()` / `format()` relations) for the list table
 * + the view infolist and NEVER writes it. Every mutation is a separate Filament Action routed through a Catalog
 * domain action (the kit's view + create pages); there is deliberately NO Edit page and NO Delete/Create default
 * action — the Catalog backend ships no update Action (post-creation field edits are out of scope, proposal
 * slice-boundary), and create lands on a write-through `ProductReferenceResource\Pages\CreateProductReference`
 * page. The PR's `(variant, format)` uniqueness is a DB-structural rule the create page surfaces as a form
 * error (design L5 — the duplicate carries no domain message, so the console owns the localized copy). The
 * no-Eloquent-write PHPStan rule (task 1.2) guards the discipline. Enums are rendered through their cast
 * instances (`->value`), never by importing `App\Modules\Catalog\Enums\*`, so the console's cross-module surface
 * stays exactly {Models, Actions} (the import-boundary carve-out). All user-facing copy is localized through the
 * `operator_console` group (invariant 12).
 */
class ProductReferenceResource extends OperatorConsoleResource
{
    protected static ?string $model = ProductReference::class;

    protected static ?int $navigationSort = 4;

    // Grouped into the Catalog "Settings" cluster (operator-console UI pass, 2026-06-24): a Product Reference is
    // a lower-level building block (Variant × Format), surfaced as a tab under Settings rather than a flat
    // top-level Catalog console.
    protected static ?string $cluster = CatalogSettings::class;

    protected static function i18nKey(): string
    {
        return 'product_reference';
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
     * The human record title — used by breadcrumbs, the view-page heading and the global-search results, so the
     * operator never reads a bare "#42". A Product Reference has no name of its own (it is the `(variant, format)`
     * key), so the title is composed from its WITHIN-Catalog parents (the carve-out the model allows): the wine
     * Master name, the vintage (the `NV` marker when non-vintage / missing), and the Format — e.g.
     * "Château Margaux — 2019 — Bottle (750ml)". A missing parent degrades gracefully to the localized
     * "Untitled" marker rather than throwing. Reads only (no Eloquent write — task 1.2); renders no
     * `Catalog\Enums` import.
     */
    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof ProductReference) {
            return null;
        }

        return self::referenceLabel($record);
    }

    /**
     * The create form (design L3/L5/L8). Collects the two inputs the Catalog `CreateProductReference` action
     * consumes — the PARENT Product Variant and the PARENT Format (both within-catalog selects; a Product
     * Reference is exactly one Variant + one Format, BR-Identity-3). The form only COLLECTS; the write routes
     * through the action in `Pages\CreateProductReference::createViaAction()` (there is no Edit page — the Catalog
     * backend ships no update Action). Neither select is a producer picker (design L6); the duplicate `(variant,
     * format)` pair is surfaced as a form error by the Create page, not pre-checked here. All labels localized
     * (invariant 12).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_variant_id')
                    ->label((string) __('operator_console.product_reference.fields.product_variant'))
                    ->options(self::productVariantOptions(...))
                    ->required(),
                Select::make('format_id')
                    ->label((string) __('operator_console.product_reference.fields.format'))
                    ->options(self::formatOptions(...))
                    ->required(),
            ]);
    }

    /**
     * The read-only list (design L1). Renders a Product Reference by its HUMAN coordinates — the parent wine
     * Master name, the Variant identifier, and the Format — via WITHIN-Catalog relation-path columns
     * (`variant.master.name`, `variant.variant_identifier`, `format.name`), so sort + search hit the DB through
     * the joined relations instead of an in-PHP `getStateUsing` (the relations the model exposes are the
     * Catalog-internal carve-out, never a cross-module join). The shared `lifecycle_state` badge closes the row,
     * and the lifecycle SelectFilter lets the operator scope by state. `applyConsoleDefaults()` supplies the
     * newest-first order + branded empty state. Pure table configuration — no Eloquent write (task 1.2).
     */
    public static function table(Table $table): Table
    {
        return static::applyConsoleDefaults($table)
            ->columns([
                TextColumn::make('variant.master.name')
                    ->label((string) __('operator_console.product_reference.columns.master'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('variant.variant_identifier')
                    ->label((string) __('operator_console.product_reference.columns.variant'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('format.name')
                    ->label((string) __('operator_console.product_reference.columns.format'))
                    ->searchable()
                    ->sortable(),
                static::lifecycleStateColumn(),
            ])
            ->filters([
                static::stateFilter(),
            ]);
    }

    /**
     * Make a Product Reference findable from the Cmd/Ctrl+K global search by its human coordinates — the parent
     * wine Master name, the Variant identifier and the Format — the same WITHIN-Catalog relation paths the list
     * carries. The localized result label resolves through {@see getRecordTitle()} (never a bare "#id").
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['variant.master.name', 'variant.variant_identifier', 'format.name'];
    }

    /**
     * The read-only view (design L1/L10). Grouped into premium, icon-headed sections — Composition (the two
     * WITHIN-Catalog parents that ARE the PR's identity: the parent wine Master + Variant identifier, and the
     * Format with its size), State (the `lifecycle_state` rendered through {@see badgedStateEntry()} as the same
     * semantic colored + iconed badge the list carries), and a collapsed Metadata section
     * ({@see metadataSection()}) for the optimistic-lock `version`. Every entry is display-only and reads only the
     * within-Catalog `variant()` / `format()` relations (never a cross-module join, invariant 10); renders no
     * `Catalog\Enums` import. All copy localized (invariant 12).
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make((string) __('operator_console.product_reference.sections.composition'))
                    ->icon('heroicon-o-cube')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('variant.master.name')
                            ->label((string) __('operator_console.product_reference.columns.master'))
                            ->weight('bold')
                            ->getStateUsing(fn (ProductReference $record): ?string => $record->variant?->master?->name),
                        TextEntry::make('variant.variant_identifier')
                            ->label((string) __('operator_console.product_reference.columns.variant'))
                            ->getStateUsing(fn (ProductReference $record): ?string => $record->variant?->variant_identifier),
                        TextEntry::make('format.name')
                            ->label((string) __('operator_console.product_reference.columns.format'))
                            ->getStateUsing(fn (ProductReference $record): ?string => $record->format?->name),
                        TextEntry::make('format.size_label')
                            ->label((string) __('operator_console.product_reference.columns.format_size'))
                            ->getStateUsing(fn (ProductReference $record): ?string => $record->format?->size_label),
                    ]),
                Section::make((string) __('operator_console.product_reference.sections.state'))
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
            'index' => Pages\ListProductReferences::route('/'),
            'create' => Pages\CreateProductReference::route('/create'),
            'view' => Pages\ViewProductReference::route('/{record}'),
        ];
    }

    /**
     * Create-form parent-Variant options, keyed by `product_variant_id` → a human `Master — identifier · state`
     * label (no bare "#id"), read from Catalog's OWN {@see ProductVariant} model and its within-Catalog
     * `master()` relation (a WITHIN-module reference — one of a PR's two parents, never a producer, design L6).
     * Eager-loads the Master so the option list resolves its name without an N+1. Creation lists every Variant;
     * the activation-cascade gate (a domain rule) is what blocks activating a PR under a non-active Variant, so
     * the picker need not pre-filter by state. The Variant's lifecycle state is rendered through its cast instance
     * (`->value`), so no `Catalog\Enums` import is needed (the {Models, Actions} surface).
     *
     * @return array<int, string>
     */
    private static function productVariantOptions(): array
    {
        return ProductVariant::query()
            ->with('master')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(static function (ProductVariant $variant): array {
                $master = $variant->master?->name;
                $head = $master !== null
                    ? $master.' — '.$variant->variant_identifier
                    : $variant->variant_identifier;

                return [$variant->id => $head.' · '.$variant->lifecycle_state->value];
            })
            ->all();
    }

    /**
     * Create-form parent-Format options, keyed by `format_id` → a human `name (size) · state` label (no bare
     * "#id"), read from Catalog's OWN {@see Format} model (a WITHIN-module reference — the PR's second parent).
     * Like the Variant picker it lists every Format; the activation-cascade gate (a domain rule) blocks activating
     * a PR under a non-active Format. The Format's lifecycle state is rendered through its cast instance
     * (`->value`), so no `Catalog\Enums` import is needed (the {Models, Actions} surface).
     *
     * @return array<int, string>
     */
    private static function formatOptions(): array
    {
        return Format::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(static fn (Format $format): array => [
                $format->id => $format->name.' ('.$format->size_label.') · '.$format->lifecycle_state->value,
            ])
            ->all();
    }

    /**
     * The human label for a Product Reference — its `(variant, format)` identity rendered as wine coordinates:
     * the parent Master name, the vintage (a four-digit year, or the localized `NV` marker when the Variant is
     * non-vintage / carries no year), and the Format with its size — e.g. "Château Margaux — 2019 — Bottle
     * (750ml)". Backs {@see getRecordTitle()}. Reads only the WITHIN-Catalog `variant()` (→ `master`,
     * `wineAttributes`) / `format()` relations (never a cross-module join, invariant 10); each absent part
     * degrades gracefully (a missing Master/Format falls back to the localized "Untitled" marker, a missing year
     * to `NV`), so the title never throws and never leaks a raw id.
     */
    private static function referenceLabel(ProductReference $record): string
    {
        $format = $record->format;

        $masterName = $record->variant?->master?->name;

        $parts = [
            $masterName ?? (string) __('operator_console.product_reference.untitled'),
            self::vintagePart($record),
            $format !== null
                ? $format->name.' ('.$format->size_label.')'
                : (string) __('operator_console.product_reference.untitled'),
        ];

        return implode(' — ', $parts);
    }

    /**
     * The vintage segment of {@see referenceLabel()}: the four-digit vintage year off the Variant's WITHIN-Catalog
     * `wineAttributes`, or the localized `NV` marker when the Variant is flagged non-vintage or carries no year.
     */
    private static function vintagePart(ProductReference $record): string
    {
        $wineAttributes = $record->variant?->wineAttributes;
        $year = $wineAttributes?->vintage_year;

        if ($wineAttributes !== null && ! $wineAttributes->non_vintage && $year !== null) {
            return (string) $year;
        }

        return (string) __('operator_console.product_reference.values.non_vintage');
    }
}
