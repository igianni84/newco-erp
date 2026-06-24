<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog;

use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * CompositeSkuResource — the operator console's READ-ONLY surface over the Catalog Composite SKU
 * (operator-console-catalog-spine, task 4.1; design L1/L3/L4; ADR 2026-06-19 + 2026-06-20). The FINAL spine
 * console and the spine's only many-to-many entity: a Composite SKU is a curated bundle of N ≥ 2 ORDERED
 * constituent Product References (Module 0 PRD §3.8, §13.5 BR-SKU-2), so its create form carries a single
 * ORDERED, N≥2 Product-Reference picker (a multi-select) — not a parent FK — and its activation-cascade gate
 * depends on EVERY constituent Product Reference being `active` (§4.4 / BR-Lifecycle-3).
 *
 * It extends {@see OperatorConsoleResource}, which owns the read-only conventions (the
 * `operator_console.<entity>` model labels off {@see i18nKey()}, the shared `lifecycle_state` badge +
 * `version` column helpers, and the no-mutating-action default); this resource supplies only its own
 * columns/form/infolist/pages plus the ordered constituents picker on the create form (design L3). Like the
 * other five spine entities it binds NO producer — the catalog is PRODUCER-AGNOSTIC about constituents (design
 * D9 / BR-SKU-5): a multi-producer bundle is accepted and the picker applies NO producer filter or validation
 * (single-producer admissibility is a Module S Offer-publication rule, never a PIM check). The N-constituent
 * activation-cascade gate (every constituent `active`) is surfaced FOR FREE by the view page's wrapper (design
 * L4), never re-checked here.
 *
 * Its ONE create guard — the `< 2 distinct constituents` floor — is a localized domain
 * `InsufficientCompositeConstituents` (a `RuntimeException`), so unlike the Product Reference duplicate (a
 * framework `UniqueConstraintViolationException` with no domain message) it needs
 * NO special framework catch: the kit base's create-rejection catch maps `$e->getMessage()` to the constituents
 * form field (design L5). It is a LEAF within Module 0 — nothing within catalog references a Composite SKU — so
 * its retire carries no within-catalog reference-integrity block (it bundles Product References; it is bundled by
 * nothing).
 *
 * It read-binds to {@see CompositeSku} — the ADR-sanctioned exception, OperatorPanel-only and display-only: the
 * resource queries the model (and its WITHIN-Catalog `constituents()` ordered junction) for the list table + the
 * view infolist and NEVER writes it. Every mutation is a separate Filament Action routed through a Catalog domain
 * action (the kit's view + create pages); there is deliberately NO Edit page and NO Delete/Create default action.
 * Enums are rendered through their cast instances (`->value`), never by importing `App\Modules\Catalog\Enums\*`,
 * so the console's cross-module surface stays exactly {Models, Actions} (the import-boundary carve-out). All
 * user-facing copy is localized through the `operator_console` group (invariant 12).
 */
class CompositeSkuResource extends OperatorConsoleResource
{
    protected static ?string $model = CompositeSku::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 6;

    protected static function i18nKey(): string
    {
        return 'composite_sku';
    }

    protected static function navigationGroupCase(): OperatorConsoleNavigationGroup
    {
        return OperatorConsoleNavigationGroup::Catalog;
    }

    /**
     * The create form (design L3/L8). A Composite SKU is attribute-free beyond its ordered constituent set
     * (§3.8 — "cheap at PIM: registration + lifecycle only"), so the form is a single ORDERED, multi-select
     * Product-Reference picker. The form only COLLECTS; the write routes through the Catalog `CreateCompositeSku`
     * action in `Pages\CreateCompositeSku::createViaAction()` (there is no Edit page). The picker lists EVERY
     * Product Reference with NO producer filter (producer-agnostic, design D9) and NO `< 2` client check — the
     * N ≥ 2 floor and the distinct-set normalisation are the domain action's job (surfaced as a form error,
     * design L5). All labels localized (invariant 12).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('constituents')
                    ->label((string) __('operator_console.composite_sku.fields.constituents'))
                    ->helperText((string) __('operator_console.composite_sku.fields.constituents_help'))
                    ->options(self::productReferenceOptions(...))
                    ->multiple()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('constituent_count')
                    ->label((string) __('operator_console.composite_sku.columns.constituent_count'))
                    ->getStateUsing(fn (CompositeSku $record): int => $record->constituents()->count()),
                static::lifecycleStateColumn(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('constituents')
                    ->label((string) __('operator_console.composite_sku.fields.constituents'))
                    ->getStateUsing(fn (CompositeSku $record): string => self::constituentsLabel($record)),
                TextEntry::make('constituent_count')
                    ->label((string) __('operator_console.composite_sku.columns.constituent_count'))
                    ->getStateUsing(fn (CompositeSku $record): int => $record->constituents()->count()),
                TextEntry::make('lifecycle_state')
                    ->label((string) __('operator_console.composite_sku.columns.lifecycle_state'))
                    ->getStateUsing(fn (CompositeSku $record): string => $record->lifecycle_state->value),
                TextEntry::make('version')
                    ->label((string) __('operator_console.composite_sku.columns.version')),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompositeSkus::route('/'),
            'create' => Pages\CreateCompositeSku::route('/create'),
            'view' => Pages\ViewCompositeSku::route('/{record}'),
        ];
    }

    /**
     * The ordered constituent set rendered as a single display string — each constituent Product Reference's
     * identity label ({@see referenceLabel()}: its Variant identifier · Format name), read off the within-Catalog
     * `constituents()` junction (already ordered by pivot `position`, so the bundle order is preserved) and joined
     * in order. Renders no `Catalog\Enums` import (the {Models, Actions} surface).
     */
    private static function constituentsLabel(CompositeSku $compositeSku): string
    {
        return $compositeSku->constituents
            ->map(fn (ProductReference $reference): string => self::referenceLabel($reference) ?? '—')
            ->implode(', ');
    }

    /**
     * The display label for one constituent Product Reference — its two identity dimensions (the Product Variant
     * identifier + the Format name), read off the within-Catalog `variant()` / `format()` relations (a PR has no
     * name of its own). Returns null when the relation is absent. Renders no `Catalog\Enums` import (the {Models,
     * Actions} surface).
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
     * Create-form constituent-Product-Reference options, keyed by `id` → a `#id · variant · format · state`
     * label, read from Catalog's OWN {@see ProductReference} model (a WITHIN-module reference — a constituent,
     * never a producer, design L6). Creation lists every Product Reference with NO producer filter
     * (producer-agnostic, design D9); the activation-cascade gate (a domain rule) is what blocks activating a
     * Composite SKU whose any constituent is non-active, so the picker need not pre-filter by state. The two
     * identity dimensions are eager-loaded for the label; the lifecycle state is rendered through its cast
     * instance (`->value`), so no `Catalog\Enums` import is needed (the {Models, Actions} surface).
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
}
