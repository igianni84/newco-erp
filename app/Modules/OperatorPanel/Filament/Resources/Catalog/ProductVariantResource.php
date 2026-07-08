<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog;

use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
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
 * the resource queries the model (and its WITHIN-Catalog `master()` / `wineAttributes()` /
 * `caseWhitelistEntries()` relations) for the list table + the view infolist and NEVER writes it. Every mutation
 * is a separate Filament Action routed through a Catalog domain action (the kit's view + create pages); there is
 * deliberately NO Edit page and NO Delete/Create default action. The reason is the read-projection discipline
 * itself, not a missing backend: since catalog-module-0-completeness-sweep the Catalog backend DOES ship update
 * Actions for a Variant (`UpdateProductVariantEnrichment`, `SetVariantCaseWhitelist`), and both are surfaced — as
 * modal header actions on the View page ({@see enrichmentEditSchema()}, {@see whitelistEditSchema()}, task 6.2 /
 * design D8), never as a Filament Edit page whose default `$record->save()` would bypass the domain. Create
 * likewise lands on a write-through `ProductVariantResource\Pages\CreateProductVariant` page. The
 * no-Eloquent-write PHPStan rule (task 1.2) guards the discipline. Enums are rendered through their cast
 * instances (`->value`), never by importing `App\Modules\Catalog\Enums\*`, so the console's cross-module surface
 * stays exactly {Models, Actions} (the import-boundary carve-out). All user-facing copy is localized through the
 * `operator_console` group (invariant 12).
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
     * Hidden from the sidebar (operator-console UI pass, 2026-06-24): Variants are seen and created INSIDE their
     * parent Product Master (see ProductMasterResource's VariantsRelationManager), not as a flat top-level
     * console. The resource stays fully registered — its list / view / create routes remain reachable (the
     * relation manager's row View action links to the view page) — only its navigation entry is suppressed.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * The create form (design L3/L8). Collects the inputs the Catalog `CreateProductVariant` action consumes —
     * the PARENT Product Master (a within-catalog select; a Variant belongs to exactly one Master,
     * BR-Identity-2), the type-neutral variant identifier, and the WINE vintage attribute set (a vintage year,
     * the non-vintage marker, and optional translatable tasting notes). The form only COLLECTS; the write routes
     * through the action in `Pages\CreateProductVariant::createViaAction()` (there is no Edit page; the
     * post-creation edits are the View page's modal actions). The Master select is sourced from Catalog's OWN
     * model, read-only and within-module — never a producer picker (design L6). All labels localized
     * (invariant 12).
     *
     * The tasting-notes input is the ONE field the create form shares with the enrichment-edit modal
     * ({@see enrichmentEditSchema()}) — a Variant's parent, identifier and vintage axis are all fixed at creation,
     * and the notes are the only enrichment the launch field set carries.
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
                self::tastingNotesField(),
            ]);
    }

    /**
     * The enrichment-edit modal's form (catalog-module-0-completeness-sweep task 6.2; design D8/R8; spec —
     * Operator maintains Variant enrichment and the Layer-1 whitelist through the console): the observational
     * enrichment `UpdateProductVariantEnrichment` replaces, which at launch is exactly the tasting-notes prose.
     *
     * It reuses the create form's own field builder, so the translatable-prose input (design R8) behaves
     * identically on both surfaces by construction rather than by copy — the same reason the Master's
     * identity-edit modal is its create form minus the producer.
     *
     * @return array<int, Component>
     */
    public static function enrichmentEditSchema(): array
    {
        return [self::tastingNotesField()];
    }

    /**
     * The enrichment-edit modal's PREFILL state, read off the Variant's 1:1 WINE attribute set.
     *
     * The notes are prefilled from — and, on submit, written back as — the ENGLISH baseline, the sole locale the
     * create form authors (`fields.tasting_notes_help`). A multi-locale prose surface is a deferred seam; until it
     * exists no other locale can be present, so replacing the value cannot lose one. (The view INFOLIST resolves
     * the active locale instead: it displays, it does not round-trip.)
     *
     * @return array<string, mixed>
     */
    public static function enrichmentEditState(ProductVariant $record): array
    {
        return ['tasting_notes' => $record->wineAttributes?->tasting_notes?->resolve('en')];
    }

    /**
     * The manage-whitelist modal's form (task 6.2; design D6/D8; spec — the J-13 reduction case): a (Variant,
     * Format) pair selector and the REPLACEMENT set of admitted Case Configurations that `SetVariantCaseWhitelist`
     * writes for it.
     *
     * The pair — not the Variant — is the unit of the Layer-1 whitelist, so the Format select is a live operand:
     * choosing one REPLACES the admitted-set field with that pair's currently admitted ids, via `$admittedIds`
     * (the page reads them off the record's within-Catalog `caseWhitelistEntries()` relation). Without that
     * re-prefill an operator who switched Format would submit the previous pair's set and silently rewrite the new
     * one — the Action takes a whole set per pair, never a patch.
     *
     * The admitted set is deliberately NOT `->required()`: an empty set is a legitimate call that CLEARS the pair,
     * restoring § 7.1's permissive default (absence admits, presence narrows).
     *
     * Both selects contribute Filament's implicit `Rule::in(<their options>)` over the same Catalog tables
     * `SetVariantCaseWhitelist` re-checks, so its `UnknownCatalogReference` rejection is a BACKSTOP this surface
     * cannot reach — proven at the domain, not here (the same layering as the Master create form's producer).
     *
     * @param  Closure(int): list<int>  $admittedIds  the pair's currently admitted Case-Configuration ids, by Format id
     * @return array<int, Component>
     */
    public static function whitelistEditSchema(Closure $admittedIds): array
    {
        return [
            Select::make('format_id')
                ->label((string) __('operator_console.product_variant.fields.whitelist_format'))
                ->options(self::formatOptions())
                ->required()
                ->live()
                ->afterStateUpdated(function (mixed $state, Set $set) use ($admittedIds): void {
                    // Re-prefill the admitted set for the newly chosen pair (an unset Format admits nothing to
                    // show). `is_numeric` narrows the Livewire-round-tripped option key to the Format id.
                    $set('case_configuration_ids', is_numeric($state) ? $admittedIds((int) $state) : []);
                }),
            Select::make('case_configuration_ids')
                ->label((string) __('operator_console.product_variant.fields.whitelist_case_configurations'))
                ->helperText((string) __('operator_console.product_variant.fields.whitelist_case_configurations_help'))
                ->options(self::caseConfigurationOptions())
                ->multiple(),
        ];
    }

    /**
     * The Variant's optional translatable tasting-notes prose — the WINE attribute set's one enrichment field
     * (§ 9.1). Shared VERBATIM by the create form and the enrichment-edit modal. Captured in English, the baseline
     * locale (design R8; the help text says so).
     */
    private static function tastingNotesField(): Textarea
    {
        return Textarea::make('tasting_notes')
            ->label((string) __('operator_console.product_variant.fields.description'))
            ->helperText((string) __('operator_console.product_variant.fields.tasting_notes_help'));
    }

    public static function table(Table $table): Table
    {
        return static::applyConsoleDefaults($table)
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
            ])
            ->filters([
                static::stateFilter(),
            ]);
    }

    /**
     * Make the Variant findable from the Cmd/Ctrl+K global search by its `variant_identifier` (the human
     * identity column; invariant 12: the label resolves through {@see getModelLabel()}). Pairs with
     * {@see $recordTitleAttribute} = 'variant_identifier'.
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['variant_identifier'];
    }

    /**
     * The read-only view (design L1/L4; FEEDBACK #4). Grouped into premium, icon-headed sections mirroring
     * Product Master — Identity (the type-neutral `variant_identifier` + the parent Master name), Classification
     * & State (the `lifecycle_state` rendered as the SAME semantic colored badge the list carries, via
     * {@see badgedStateEntry()}), Vintage & Attributes (the WINE attribute set: the vintage year, the non-vintage
     * marker, and the per-locale tasting-notes prose surfaced as the Variant's Description, resolved to the
     * active locale), and a collapsed Metadata section for the optimistic-lock `version`. Every entry is
     * display-only; the WINE attributes resolve through the within-Catalog {@see ProductVariant::wineAttributes()}
     * relation (never a `Catalog\Enums` import — the {Models, Actions} surface). All copy localized (invariant 12).
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make((string) __('operator_console.product_variant.sections.identity'))
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('variant_identifier')
                            ->label((string) __('operator_console.product_variant.columns.variant_identifier'))
                            ->weight('bold'),
                        TextEntry::make('master')
                            ->label((string) __('operator_console.product_variant.columns.master'))
                            ->getStateUsing(fn (ProductVariant $record): ?string => $record->master?->name),
                    ]),
                Section::make((string) __('operator_console.product_variant.sections.classification'))
                    ->icon('heroicon-o-check-badge')
                    ->columns(2)
                    ->schema([
                        static::badgedStateEntry(),
                    ]),
                Section::make((string) __('operator_console.product_variant.sections.attributes'))
                    ->icon('heroicon-o-beaker')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('vintage_year')
                            ->label((string) __('operator_console.product_variant.fields.vintage_year'))
                            ->getStateUsing(fn (ProductVariant $record): ?int => $record->wineAttributes?->vintage_year),
                        TextEntry::make('non_vintage')
                            ->label((string) __('operator_console.product_variant.fields.non_vintage'))
                            ->getStateUsing(fn (ProductVariant $record): string => $record->wineAttributes?->non_vintage
                                ? (string) __('operator_console.product_variant.values.yes')
                                : (string) __('operator_console.product_variant.values.no')),
                        TextEntry::make('tasting_notes')
                            ->label((string) __('operator_console.product_variant.fields.description'))
                            ->columnSpanFull()
                            ->getStateUsing(fn (ProductVariant $record): ?string => $record->wineAttributes?->tasting_notes?->resolve(app()->getLocale())),
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
     * Create-form parent-Master options, keyed by `product_master_id` → a human `name · state` label (the
     * Master's display NAME leading, never a bare `#id` — the premium picker convention), read from Catalog's
     * OWN {@see ProductMaster} model (a WITHIN-module reference — a Variant's single parent, never a producer,
     * design L6). Creation lists every Master; the activation-cascade gate (a domain rule) is what blocks
     * activating a Variant under a non-active Master, so the picker need not pre-filter by state. The Master's
     * lifecycle state is rendered through its cast instance (`->value`), so no `Catalog\Enums` import is needed
     * (the {Models, Actions} surface).
     *
     * @return array<int, string>
     */
    private static function productMasterOptions(): array
    {
        return ProductMaster::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(static fn (ProductMaster $master): array => [
                $master->id => $master->name.' · '.$master->lifecycle_state->value,
            ])
            ->all();
    }

    /**
     * Manage-whitelist modal Format options, keyed by `format_id` → the same `name (size) · state` label the
     * Product Reference picker renders. Every Format is listed, whatever its lifecycle state: a whitelist catalogs
     * PACKAGING POSSIBILITY ahead of readiness (design D6), and it is the Sellable-SKU activation gate — not this
     * surface — that requires an `active` Case Configuration.
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
     * Manage-whitelist modal Case-Configuration options, keyed by `case_configuration_id` → the configuration's
     * name (the Sellable SKU picker's convention). Unfiltered by lifecycle state, for the reason above.
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
