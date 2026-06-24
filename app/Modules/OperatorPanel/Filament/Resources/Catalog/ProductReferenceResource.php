<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog;

use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 4;

    protected static function i18nKey(): string
    {
        return 'product_reference';
    }

    protected static function navigationGroupCase(): OperatorConsoleNavigationGroup
    {
        return OperatorConsoleNavigationGroup::Catalog;
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('variant')
                    ->label((string) __('operator_console.product_reference.columns.variant'))
                    ->getStateUsing(fn (ProductReference $record): ?string => $record->variant?->variant_identifier),
                TextColumn::make('format')
                    ->label((string) __('operator_console.product_reference.columns.format'))
                    ->getStateUsing(fn (ProductReference $record): ?string => $record->format?->name),
                static::lifecycleStateColumn(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('variant')
                    ->label((string) __('operator_console.product_reference.columns.variant'))
                    ->getStateUsing(fn (ProductReference $record): ?string => $record->variant?->variant_identifier),
                TextEntry::make('format')
                    ->label((string) __('operator_console.product_reference.columns.format'))
                    ->getStateUsing(fn (ProductReference $record): ?string => $record->format?->name),
                TextEntry::make('lifecycle_state')
                    ->label((string) __('operator_console.product_reference.columns.lifecycle_state'))
                    ->getStateUsing(fn (ProductReference $record): string => $record->lifecycle_state->value),
                TextEntry::make('version')
                    ->label((string) __('operator_console.product_reference.columns.version')),
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
     * Create-form parent-Variant options, keyed by `product_variant_id` → a `#id · identifier · state` label,
     * read from Catalog's OWN {@see ProductVariant} model (a WITHIN-module reference — one of a PR's two parents,
     * never a producer, design L6). Creation lists every Variant; the activation-cascade gate (a domain rule) is
     * what blocks activating a PR under a non-active Variant, so the picker need not pre-filter by state. The
     * Variant's lifecycle state is rendered through its cast instance (`->value`), so no `Catalog\Enums` import is
     * needed (the {Models, Actions} surface).
     *
     * @return array<int, string>
     */
    private static function productVariantOptions(): array
    {
        return ProductVariant::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(static fn (ProductVariant $variant): array => [
                $variant->id => '#'.$variant->id.' · '.$variant->variant_identifier.' · '.$variant->lifecycle_state->value,
            ])
            ->all();
    }

    /**
     * Create-form parent-Format options, keyed by `format_id` → a `#id · name · size · state` label, read from
     * Catalog's OWN {@see Format} model (a WITHIN-module reference — the PR's second parent). Like the Variant
     * picker it lists every Format; the activation-cascade gate (a domain rule) blocks activating a PR under a
     * non-active Format. The Format's lifecycle state is rendered through its cast instance (`->value`), so no
     * `Catalog\Enums` import is needed (the {Models, Actions} surface).
     *
     * @return array<int, string>
     */
    private static function formatOptions(): array
    {
        return Format::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(static fn (Format $format): array => [
                $format->id => '#'.$format->id.' · '.$format->name.' · '.$format->size_label.' · '.$format->lifecycle_state->value,
            ])
            ->all();
    }
}
