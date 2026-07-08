<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog;

use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource\Pages;
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
 * SellableSkuResource — the operator console's READ-ONLY surface over the Catalog Sellable SKU (Intrinsic)
 * (operator-console-catalog-spine, task 3.3; design L1/L3/L4; ADR 2026-06-19 + 2026-06-20). The third
 * HIERARCHICAL spine console, built — like Product Variant / Product Reference — as PURE reuse of the kit
 * extracted in tasks 1.1/1.2. A Sellable SKU is the commercial unit composed of EXACTLY one Product Reference +
 * one Case Configuration + commercial attributes (a commercial name and optional marketing copy), so its create
 * form carries TWO parent pickers (a Product Reference + a Case Configuration) plus the commercial fields, and
 * its activation-cascade gate depends on BOTH parents being `active` (§3.7 / BR-Lifecycle-3).
 *
 * It extends {@see OperatorConsoleResource}, which owns the read-only conventions (the
 * `operator_console.<entity>` model labels off {@see i18nKey()}, the shared `lifecycle_state` badge +
 * `version` column helpers, and the no-mutating-action default); this resource supplies only its own
 * columns/form/infolist/pages plus the two parent pickers + commercial fields on the create form (design L3).
 * Unlike Product Master it binds NO producer — there is no producer picker and no Producer-gate handling
 * (design L6); its only parents are the within-catalog Product Reference and Case Configuration, and the
 * activation-cascade gate (SKU ← Product Reference AND Case Configuration active) is surfaced FOR FREE by the
 * view page's wrapper (design L4), never re-checked here.
 *
 * Unlike the Product Reference (whose `(variant, format)` pair is unique), a Sellable SKU has NO uniqueness rule:
 * the same Product Reference + Case Configuration pair may legitimately back MORE than one SKU (the "packaging
 * does not change the PR" rule — BR-SKU-1), so there is NO create guard and no duplicate form-error. It is also a
 * LEAF within Module 0 — nothing within catalog references it, so its retire carries no within-catalog
 * reference-integrity block.
 *
 * It read-binds to {@see SellableSku} — the ADR-sanctioned exception, OperatorPanel-only and display-only: the
 * resource queries the model (and its WITHIN-Catalog `reference()` / `caseConfiguration()` relations) for the
 * list table + the view infolist and NEVER writes it. Every mutation is a separate Filament Action routed
 * through a Catalog domain action (the kit's view + create pages); there is deliberately NO Edit page and NO
 * Delete/Create default action. The reason is twofold, and only half of it is about the backend: the Catalog
 * backend ships no update Action FOR THIS ENTITY (catalog-module-0-completeness-sweep added edit Actions only
 * for a Master's identity, a Composite's composition and a Variant's enrichment + whitelist — design D2), and
 * even where an edit DOES exist the console surfaces it as a modal header action on the View page, never as an
 * Edit page whose default `$record->save()` would bypass the domain (design D8). Create lands on a
 * write-through `SellableSkuResource\Pages\CreateSellableSku` page. The no-Eloquent-write PHPStan rule (task
 * 1.2) guards the discipline. Enums are rendered through their cast instances (`->value`), never by importing
 * `App\Modules\Catalog\Enums\*`, so the console's cross-module surface stays exactly {Models, Actions} (the
 * import-boundary carve-out). All user-facing copy is localized through the `operator_console` group
 * (invariant 12).
 */
class SellableSkuResource extends OperatorConsoleResource
{
    protected static ?string $model = SellableSku::class;

    protected static ?string $recordTitleAttribute = 'commercial_name';

    protected static ?int $navigationSort = 5;

    protected static function i18nKey(): string
    {
        return 'sellable_sku';
    }

    protected static function navigationGroupCase(): OperatorConsoleNavigationGroup
    {
        return OperatorConsoleNavigationGroup::Catalog;
    }

    /**
     * The create form (design L3/L8). Collects the inputs the Catalog `CreateSellableSku` action consumes —
     * the PARENT Product Reference and the PARENT Case Configuration (both within-catalog selects; a Sellable
     * SKU is exactly one Product Reference + one Case Configuration, BR-SKU-1), the commercial name, and
     * optional marketing copy. The form only COLLECTS; the write routes through the action in
     * `Pages\CreateSellableSku::createViaAction()` (there is no Edit page — the Catalog backend ships no
     * update Action for this entity, and the edits that DO exist elsewhere in Module 0 are modal header
     * actions on their View page, never Edit pages). Neither select is a producer picker (design L6); a
     * Sellable SKU has no uniqueness rule, so there is no duplicate pre-check (contrast the Product
     * Reference). All labels localized (invariant 12).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_reference_id')
                    ->label((string) __('operator_console.sellable_sku.fields.product_reference'))
                    ->options(self::productReferenceOptions(...))
                    ->required(),
                Select::make('case_configuration_id')
                    ->label((string) __('operator_console.sellable_sku.fields.case_configuration'))
                    ->options(self::caseConfigurationOptions(...))
                    ->required(),
                TextInput::make('commercial_name')
                    ->label((string) __('operator_console.sellable_sku.fields.commercial_name'))
                    ->required()
                    ->maxLength(255),
                Textarea::make('marketing_copy')
                    ->label((string) __('operator_console.sellable_sku.fields.marketing_copy')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyConsoleDefaults($table)
            ->columns([
                TextColumn::make('commercial_name')
                    ->label((string) __('operator_console.sellable_sku.columns.commercial_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reference')
                    ->label((string) __('operator_console.sellable_sku.columns.reference'))
                    ->getStateUsing(fn (SellableSku $record): ?string => self::referenceLabel($record->reference)),
                TextColumn::make('caseConfiguration')
                    ->label((string) __('operator_console.sellable_sku.columns.case_configuration'))
                    ->getStateUsing(fn (SellableSku $record): ?string => $record->caseConfiguration?->name),
                static::lifecycleStateColumn(),
            ])
            ->filters([
                static::stateFilter(),
            ]);
    }

    /**
     * Make the Sellable SKU findable from the Cmd/Ctrl+K global search by its commercial name (invariant 12: the
     * label resolves through {@see getModelLabel()}). Pairs with {@see $recordTitleAttribute} = 'commercial_name'.
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['commercial_name'];
    }

    /**
     * The read-only view (design L10). Grouped into premium, icon-headed sections — Commercial Identity (the
     * commercial name + optional marketing copy), Composition (the two within-Catalog parents: the resolved
     * Product Reference human label + the Case Configuration name), and State (the `lifecycle_state` rendered as
     * the same semantic colored + iconed badge the list carries, via {@see badgedStateEntry()}), closing with a
     * collapsed Metadata section for the optimistic-lock `version`. Every entry is display-only; the parent labels
     * resolve off the within-Catalog `reference()` / `caseConfiguration()` relations (never Module K / Module S).
     * All copy localized (invariant 12).
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make((string) __('operator_console.sellable_sku.sections.identity'))
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('commercial_name')
                            ->label((string) __('operator_console.sellable_sku.columns.commercial_name'))
                            ->weight('bold'),
                        TextEntry::make('marketing_copy')
                            ->label((string) __('operator_console.sellable_sku.fields.marketing_copy'))
                            ->columnSpanFull()
                            ->placeholder((string) __('operator_console.sellable_sku.placeholders.no_marketing_copy')),
                    ]),
                Section::make((string) __('operator_console.sellable_sku.sections.composition'))
                    ->icon('heroicon-o-cube')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reference')
                            ->label((string) __('operator_console.sellable_sku.columns.reference'))
                            ->getStateUsing(fn (SellableSku $record): ?string => self::referenceLabel($record->reference)),
                        TextEntry::make('caseConfiguration')
                            ->label((string) __('operator_console.sellable_sku.columns.case_configuration'))
                            ->getStateUsing(fn (SellableSku $record): ?string => $record->caseConfiguration?->name),
                    ]),
                Section::make((string) __('operator_console.sellable_sku.sections.state'))
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
            'index' => Pages\ListSellableSkus::route('/'),
            'create' => Pages\CreateSellableSku::route('/create'),
            'view' => Pages\ViewSellableSku::route('/{record}'),
        ];
    }

    /**
     * The HUMAN display label for a Sellable SKU's parent Product Reference — the wine identity a person reads, NOT
     * an opaque id: the parent Master NAME, the vintage, and the Format (e.g. "Château Margaux — 2019 — Bottle
     * (750ml)"). A Product Reference has no name of its own, so the label is composed off the within-Catalog
     * `variant()` → `master()` / `wineAttributes()` and `format()` relations. Returns null when the SKU's
     * `reference()` relation is absent. Renders no `Catalog\Enums` import (the {Models, Actions} surface).
     */
    private static function referenceLabel(?ProductReference $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        // Bind the within-Catalog relations to locals and narrow each with an explicit `=== null` check before the
        // `->` read (the `nullsafe.neverNull` gotcha — a `?->x ?? '—'` is flagged at phpstan max; an explicit
        // narrow reads the nullable relation cleanly).
        $variant = $reference->variant;
        $format = $reference->format;

        $masterLabel = ($variant === null || $variant->master === null)
            ? (string) __('operator_console.sellable_sku.unnamed_reference')
            : $variant->master->name;

        $vintageLabel = $variant === null ? null : self::vintageLabel($variant);
        $formatLabel = $format === null ? null : self::formatLabel($format);

        return implode(' — ', array_filter([
            $masterLabel,
            $vintageLabel,
            $formatLabel,
        ], static fn (?string $part): bool => $part !== null && $part !== ''));
    }

    /**
     * The vintage segment of a reference label: the WINE vintage year off the variant's 1:1 attribute set
     * ({@see ProductVariant::wineAttributes()}), the localized non-vintage marker when the wine is explicitly NV,
     * or the neutral `variant_identifier` as the fallback when no attribute set is attached. A WITHIN-Catalog read;
     * no `Catalog\Enums` import.
     */
    private static function vintageLabel(ProductVariant $variant): string
    {
        $wineAttributes = $variant->wineAttributes;

        if ($wineAttributes === null) {
            return $variant->variant_identifier;
        }

        if ($wineAttributes->vintage_year !== null) {
            return (string) $wineAttributes->vintage_year;
        }

        if ($wineAttributes->non_vintage) {
            return (string) __('operator_console.sellable_sku.non_vintage');
        }

        return $variant->variant_identifier;
    }

    /**
     * The Format segment of a label — the bottle-size name with its size in parentheses (e.g. "Bottle (750ml)"),
     * or the bare name when no size label is set. A WITHIN-Catalog read; no `Catalog\Enums` import.
     */
    private static function formatLabel(Format $format): string
    {
        $size = $format->size_label;

        return $size === '' ? $format->name : $format->name.' ('.$size.')';
    }

    /**
     * Create-form parent-Product-Reference options, keyed by `product_reference_id` → the SAME human wine label the
     * list + view carry ({@see referenceLabel()}: Master name — vintage — Format, e.g. "Château Margaux — 2019 —
     * Bottle (750ml)") — NEVER a raw `#id`, which a person cannot recognise (feedback #5). Read from Catalog's OWN
     * {@see ProductReference} model (a WITHIN-module reference — one of a SKU's two parents, never a producer,
     * design L6); the within-Catalog `variant.master` / `variant.wineAttributes` / `format` relations are
     * eager-loaded so the label resolves without an N+1. Creation lists every Product Reference; the
     * activation-cascade gate (a domain rule) is what blocks activating a SKU under a non-active Product Reference,
     * so the picker need not pre-filter by state, and the lifecycle is NOT part of a human SKU label. No
     * `Catalog\Enums` import (the {Models, Actions} surface).
     *
     * @return array<int, string>
     */
    private static function productReferenceOptions(): array
    {
        return ProductReference::query()
            ->with(['variant.master', 'variant.wineAttributes', 'format'])
            ->orderBy('id')
            ->get()
            ->mapWithKeys(static fn (ProductReference $reference): array => [
                $reference->id => self::referenceLabel($reference) ?? '',
            ])
            ->all();
    }

    /**
     * Create-form parent-Case-Configuration options, keyed by `case_configuration_id` → the Case Configuration's
     * human NAME — NEVER a raw `#id` (feedback #5). Read from Catalog's OWN {@see CaseConfiguration} model (a
     * WITHIN-module reference — the SKU's second parent). Like the Product Reference picker it lists every Case
     * Configuration; the activation-cascade gate (a domain rule) blocks activating a SKU under a non-active Case
     * Configuration, so the picker need not pre-filter by state. No `Catalog\Enums` import (the {Models, Actions}
     * surface).
     *
     * @return array<int, string>
     */
    private static function caseConfigurationOptions(): array
    {
        return CaseConfiguration::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(static fn (CaseConfiguration $caseConfiguration): array => [
                $caseConfiguration->id => $caseConfiguration->name,
            ])
            ->all();
    }
}
