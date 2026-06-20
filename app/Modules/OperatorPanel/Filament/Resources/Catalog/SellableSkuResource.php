<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog;

use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
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
 * list table + the view infolist and NEVER writes it. Every mutation is a separate Filament Action routed through
 * a Catalog domain action (the kit's view + create pages); there is deliberately NO Edit page and NO
 * Delete/Create default action — the Catalog backend ships no update Action (post-creation field edits are out of
 * scope, proposal slice-boundary), and create lands on a write-through
 * `SellableSkuResource\Pages\CreateSellableSku` page. The no-Eloquent-write PHPStan rule (task 1.2) guards the
 * discipline. Enums are rendered through their cast instances (`->value`), never by importing
 * `App\Modules\Catalog\Enums\*`, so the console's cross-module surface stays exactly {Models, Actions} (the
 * import-boundary carve-out). All user-facing copy is localized through the `operator_console` group
 * (invariant 12).
 */
class SellableSkuResource extends OperatorConsoleResource
{
    protected static ?string $model = SellableSku::class;

    protected static ?string $recordTitleAttribute = 'commercial_name';

    protected static function i18nKey(): string
    {
        return 'sellable_sku';
    }

    /**
     * The create form (design L3/L8). Collects the inputs the Catalog `CreateSellableSku` action consumes — the
     * PARENT Product Reference and the PARENT Case Configuration (both within-catalog selects; a Sellable SKU is
     * exactly one Product Reference + one Case Configuration, BR-SKU-1), the commercial name, and optional
     * marketing copy. The form only COLLECTS; the write routes through the action in
     * `Pages\CreateSellableSku::createViaAction()` (there is no Edit page — the Catalog backend ships no update
     * Action). Neither select is a producer picker (design L6); a Sellable SKU has no uniqueness rule, so there is
     * no duplicate pre-check (contrast the Product Reference). All labels localized (invariant 12).
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
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label((string) __('operator_console.sellable_sku.columns.reference'))
                    ->getStateUsing(fn (SellableSku $record): ?string => self::referenceLabel($record->reference)),
                TextColumn::make('caseConfiguration')
                    ->label((string) __('operator_console.sellable_sku.columns.case_configuration'))
                    ->getStateUsing(fn (SellableSku $record): ?string => $record->caseConfiguration?->name),
                TextColumn::make('commercial_name')
                    ->label((string) __('operator_console.sellable_sku.columns.commercial_name'))
                    ->searchable()
                    ->sortable(),
                static::lifecycleStateColumn(),
                static::versionColumn(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('reference')
                    ->label((string) __('operator_console.sellable_sku.columns.reference'))
                    ->getStateUsing(fn (SellableSku $record): ?string => self::referenceLabel($record->reference)),
                TextEntry::make('caseConfiguration')
                    ->label((string) __('operator_console.sellable_sku.columns.case_configuration'))
                    ->getStateUsing(fn (SellableSku $record): ?string => $record->caseConfiguration?->name),
                TextEntry::make('commercial_name')
                    ->label((string) __('operator_console.sellable_sku.columns.commercial_name')),
                TextEntry::make('marketing_copy')
                    ->label((string) __('operator_console.sellable_sku.fields.marketing_copy')),
                TextEntry::make('lifecycle_state')
                    ->label((string) __('operator_console.sellable_sku.columns.lifecycle_state'))
                    ->getStateUsing(fn (SellableSku $record): string => $record->lifecycle_state->value),
                TextEntry::make('version')
                    ->label((string) __('operator_console.sellable_sku.columns.version')),
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
     * The display label for a Sellable SKU's parent Product Reference — its two identity dimensions (the Product
     * Variant identifier + the Format name), read off the within-Catalog `variant()` / `format()` relations (a PR
     * has no name of its own). Returns null when the SKU's `reference()` relation is absent. Renders no
     * `Catalog\Enums` import (the {Models, Actions} surface).
     */
    private static function referenceLabel(?ProductReference $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        // Bind the within-Catalog `variant()` / `format()` relations to locals and narrow each with an explicit
        // `=== null` ternary before the `->` read (the 3.1 `nullsafe.neverNull` gotcha — a `?->x ?? '—'` is
        // flagged at phpstan max; the ternary narrows the nullable relation cleanly).
        $variant = $reference->variant;
        $format = $reference->format;

        $variantLabel = $variant === null ? '—' : $variant->variant_identifier;
        $formatLabel = $format === null ? '—' : $format->name;

        return $variantLabel.' · '.$formatLabel;
    }

    /**
     * Create-form parent-Product-Reference options, keyed by `product_reference_id` → a
     * `#id · variant · format · state` label, read from Catalog's OWN {@see ProductReference} model (a
     * WITHIN-module reference — one of a SKU's two parents, never a producer, design L6). Creation lists every
     * Product Reference; the activation-cascade gate (a domain rule) is what blocks activating a SKU under a
     * non-active Product Reference, so the picker need not pre-filter by state. The two identity dimensions are
     * eager-loaded for the label; the lifecycle state is rendered through its cast instance (`->value`), so no
     * `Catalog\Enums` import is needed (the {Models, Actions} surface).
     *
     * @return array<int, string>
     */
    private static function productReferenceOptions(): array
    {
        return ProductReference::query()
            ->with(['variant', 'format'])
            ->orderBy('id')
            ->get()
            ->mapWithKeys(static function (ProductReference $reference): array {
                $variant = $reference->variant;
                $format = $reference->format;

                $variantLabel = $variant === null ? '—' : $variant->variant_identifier;
                $formatLabel = $format === null ? '—' : $format->name;

                return [
                    $reference->id => '#'.$reference->id.' · '.$variantLabel.' · '.$formatLabel.' · '.$reference->lifecycle_state->value,
                ];
            })
            ->all();
    }

    /**
     * Create-form parent-Case-Configuration options, keyed by `case_configuration_id` → a `#id · name · state`
     * label, read from Catalog's OWN {@see CaseConfiguration} model (a WITHIN-module reference — the SKU's second
     * parent). Like the Product Reference picker it lists every Case Configuration; the activation-cascade gate (a
     * domain rule) blocks activating a SKU under a non-active Case Configuration. The lifecycle state is rendered
     * through its cast instance (`->value`), so no `Catalog\Enums` import is needed (the {Models, Actions}
     * surface).
     *
     * @return array<int, string>
     */
    private static function caseConfigurationOptions(): array
    {
        return CaseConfiguration::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(static fn (CaseConfiguration $caseConfiguration): array => [
                $caseConfiguration->id => '#'.$caseConfiguration->id.' · '.$caseConfiguration->name.' · '.$caseConfiguration->lifecycle_state->value,
            ])
            ->all();
    }
}
