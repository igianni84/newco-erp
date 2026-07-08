<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog;

use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * CompositeSkuResource — the operator console's READ-ONLY surface over the Catalog Composite SKU
 * (operator-console-catalog-spine, task 4.1; premium polish operator-console UI pass, 2026-06-24; design
 * L1/L3/L4; ADR 2026-06-19 + 2026-06-20). The FINAL spine console and the spine's only many-to-many entity: a
 * Composite SKU is a curated bundle of N ≥ 2 ORDERED constituent Product References (Module 0 PRD §3.8, §13.5
 * BR-SKU-2), so its create form carries a single ORDERED, N≥2 Product-Reference picker (a multi-select) — not a
 * parent FK — and its activation-cascade gate depends on EVERY constituent Product Reference being `active`
 * (§4.4 / BR-Lifecycle-3).
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
 * Because a Composite SKU is attribute-free beyond its ordered constituent set (§3.8 — it has no name of its
 * own), every operator-facing label is DERIVED from the constituents: the list carries a human bundle summary
 * (`Bundle of N — <first constituent>`) and the view renders the constituents one-per-row through a
 * {@see RepeatableEntry}, each row showing its bundle position, the constituent Product Reference's human label
 * (Master — vintage — format, never a bare `#id`) and that reference's own lifecycle as a semantic badge. The
 * record title is the same human bundle summary ({@see getRecordTitle()}) so breadcrumbs / global links never
 * read as a bare integer id.
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
 * The reason is the read-projection discipline itself, not a missing backend: since
 * catalog-module-0-completeness-sweep the Catalog backend DOES ship an update Action
 * (`UpdateCompositeSkuComposition` — a Composite's constituent set IS its identity), and post-creation composition
 * edits are surfaced — as a modal header action on the View page ({@see compositionEditSchema()}, design D8), never
 * as a Filament Edit page whose default `$record->save()` would bypass the domain.
 * Enums are rendered through their cast instances (`->value`), never by importing `App\Modules\Catalog\Enums\*`,
 * so the console's cross-module surface stays exactly {Models, Actions} (the import-boundary carve-out). All
 * user-facing copy is localized through the `operator_console` group (invariant 12).
 */
class CompositeSkuResource extends OperatorConsoleResource
{
    protected static ?string $model = CompositeSku::class;

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
     * The record's human title — a Composite SKU has no name of its own (it is attribute-free beyond its
     * ordered constituent set, §3.8), so the bare integer id is never shown to an operator. The title is the
     * same human bundle summary the list carries ({@see bundleSummary()}: `Bundle of N — <first constituent>`),
     * so breadcrumbs and global links read meaningfully. Replaces a `$recordTitleAttribute = 'id'` (banned).
     */
    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof CompositeSku ? self::bundleSummary($record) : null;
    }

    /**
     * The create form (design L3/L8). A Composite SKU is attribute-free beyond its ordered constituent set
     * (§3.8 — "cheap at PIM: registration + lifecycle only"), so the form is a single ORDERED, multi-select
     * Product-Reference picker. The form only COLLECTS; the write routes through the Catalog `CreateCompositeSku`
     * action in `Pages\CreateCompositeSku::createViaAction()` (there is no Edit page). The picker lists EVERY
     * Product Reference by its human label (Master — vintage — format, never a bare `#id`) with NO producer
     * filter (producer-agnostic, design D9) and NO `< 2` client check — the N ≥ 2 floor and the distinct-set
     * normalisation are the domain action's job (surfaced as a form error, design L5). All labels localized
     * (invariant 12).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::constituentsField(),
            ]);
    }

    /**
     * The composition-edit modal's form (catalog-module-0-completeness-sweep task 6.3; design D8; spec — Operator
     * edits catalog identity content through the console): the ONE operand `UpdateCompositeSkuComposition`
     * replaces — the ordered constituent Product Reference set, which for this attribute-free entity (§3.8) IS its
     * identity.
     *
     * It is the create form, verbatim: the same field builder, so the ordered picker, its option labels and its
     * producer-agnostic breadth behave identically on both surfaces by construction rather than by copy (the same
     * reason the Master's identity-edit modal is its create form minus the producer). The floors it does NOT
     * enforce are the point: the `required()` rule only refuses an EMPTY selection, so a one-element edit reaches
     * the domain's `N ≥ 2 distinct` floor, and no constituent-state rule is evaluated here at all — the
     * active-Composite cascade re-assert is the Action's (design L4: surface, don't reimplement).
     *
     * @return array<int, Component>
     */
    public static function compositionEditSchema(): array
    {
        return [self::constituentsField()];
    }

    /**
     * The composition-edit modal's PREFILL state — the record's CURRENT constituent ids in bundle `position`
     * order, read off the within-Catalog `constituents()` junction (already `orderByPivot`).
     *
     * Order is content here (`UpdateCompositeSkuComposition` compares the ordered lists element-wise), so the
     * prefill must never sort: it hands the multi-select the bundle exactly as stored, and an operator who
     * submits untouched re-affirms the same order. The ids are hydrated and cast rather than `pluck()`ed —
     * `pluck()` is `mixed` to static analysis, while the model's `@property int` earns the `list<int>` a Filament
     * multi-select's state needs (the same read the Action performs).
     *
     * @return array<string, mixed>
     */
    public static function compositionEditState(CompositeSku $record): array
    {
        return [
            'constituents' => array_values(
                $record->constituents()
                    ->get()
                    ->map(fn (ProductReference $constituent): int => $constituent->id)
                    ->all()
            ),
        ];
    }

    /**
     * The ordered, N≥2 constituent Product-Reference picker — a Composite SKU's ONLY operand. Shared VERBATIM by
     * the create form and the composition-edit modal ({@see compositionEditSchema()}).
     */
    private static function constituentsField(): Select
    {
        return Select::make('constituents')
            ->label((string) __('operator_console.composite_sku.fields.constituents'))
            ->helperText((string) __('operator_console.composite_sku.fields.constituents_help'))
            ->options(self::productReferenceOptions(...))
            ->multiple()
            ->required();
    }

    public static function table(Table $table): Table
    {
        return static::applyConsoleDefaults($table)
            ->columns([
                // The derived, READABLE bundle summary — a Composite SKU has no name of its own, so the list
                // leads with `Bundle of N — <first constituent>` instead of a bare id. A computed column (no DB
                // column backs it), so it is intentionally neither sortable nor searchable.
                TextColumn::make('bundle')
                    ->label((string) __('operator_console.composite_sku.columns.bundle'))
                    ->weight('bold')
                    ->getStateUsing(fn (CompositeSku $record): string => self::bundleSummary($record)),
                // The bundle size, sortable via a `withCount('constituents')` aggregate (the `constituents_count`
                // attribute) — Filament both resolves the displayed count and orders by it, so no per-row count
                // query and no non-DB `getStateUsing` sort (which would throw).
                TextColumn::make('constituents_count')
                    ->label((string) __('operator_console.composite_sku.columns.constituent_count'))
                    ->counts('constituents')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                static::lifecycleStateColumn(),
            ])
            ->filters([
                static::stateFilter(),
            ]);
    }

    /**
     * The read-only view (design L4; premium grouped infolist, operator-console UI pass 2026-06-24). The SKU's
     * own `lifecycle_state` renders as the SAME semantic colored + iconed badge the list carries
     * ({@see badgedStateEntry()}); the ordered constituent set is rendered one-per-row through a
     * {@see RepeatableEntry} over the within-Catalog `constituents()` junction (already ordered by pivot
     * `position`), each row showing its 1-based bundle position, the constituent Product Reference's human label
     * ({@see referenceLabel()}: Master — vintage — format, never a bare `#id`) and that reference's OWN
     * lifecycle as a semantic badge; the view closes with a collapsed Metadata section (the optimistic-lock
     * `version`). Every entry is display-only and all copy is localized (invariant 12).
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make((string) __('operator_console.composite_sku.sections.state'))
                    ->icon('heroicon-o-check-badge')
                    ->columns(2)
                    ->schema([
                        static::badgedStateEntry(),
                        TextEntry::make('constituents_count')
                            ->label((string) __('operator_console.composite_sku.columns.constituent_count'))
                            ->badge()
                            ->color('primary')
                            ->counts('constituents'),
                    ]),
                Section::make((string) __('operator_console.composite_sku.sections.constituents'))
                    ->icon('heroicon-o-squares-2x2')
                    ->schema([
                        RepeatableEntry::make('constituents')
                            ->label((string) __('operator_console.composite_sku.fields.constituents'))
                            ->columns(3)
                            ->schema([
                                TextEntry::make('position')
                                    ->label((string) __('operator_console.composite_sku.columns.position'))
                                    ->getStateUsing(fn (ProductReference $record): string => self::positionLabel($record)),
                                TextEntry::make('reference')
                                    ->label((string) __('operator_console.composite_sku.columns.reference'))
                                    ->weight('bold')
                                    ->columnSpan(2)
                                    ->getStateUsing(fn (ProductReference $record): string => self::referenceLabel($record)),
                                TextEntry::make('reference_state')
                                    ->label((string) __('operator_console.composite_sku.columns.reference_state'))
                                    ->badge()
                                    ->color(fn (string $state): string => static::stateBadgeColor($state))
                                    ->icon(fn (string $state): ?string => static::stateBadgeIcon($state))
                                    ->getStateUsing(fn (ProductReference $record): string => $record->lifecycle_state->value),
                            ]),
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
            'index' => Pages\ListCompositeSkus::route('/'),
            'create' => Pages\CreateCompositeSku::route('/create'),
            'view' => Pages\ViewCompositeSku::route('/{record}'),
        ];
    }

    /**
     * The human one-line bundle summary — `Bundle of N — <first constituent>` (e.g. "Bundle of 3 — Romanée-Conti
     * — 2019 — Bottle (750ml)"). Powers both the list's lead column and {@see getRecordTitle()} so a Composite
     * SKU is never read as a bare integer id. Reads the ordered within-Catalog `constituents()` junction (already
     * ordered by pivot `position`); falls back to a localized empty marker when the bundle has no constituents
     * yet. Renders no `Catalog\Enums` import (the {Models, Actions} surface).
     */
    private static function bundleSummary(CompositeSku $compositeSku): string
    {
        $constituents = $compositeSku->constituents;
        $count = $constituents->count();

        if ($count === 0) {
            return (string) __('operator_console.composite_sku.bundle_empty');
        }

        $first = $constituents->first();
        $firstLabel = $first instanceof ProductReference ? self::referenceLabel($first) : '—';

        return (string) __('operator_console.composite_sku.bundle_summary', [
            'count' => $count,
            'first' => $firstLabel,
        ]);
    }

    /**
     * The 1-based bundle position of a constituent, read off the ordered `constituents()` pivot (`position`).
     * The relation orders by pivot `position`, so the stored value IS the bundle order; rendered with the
     * localized "Position N" prefix. Null pivot (a malformed link) falls back to a dash.
     */
    private static function positionLabel(ProductReference $reference): string
    {
        $pivot = $reference->getAttribute('pivot');
        $position = $pivot instanceof Model ? $pivot->getAttribute('position') : null;

        if (! is_numeric($position)) {
            return '—';
        }

        return (string) __('operator_console.composite_sku.position_value', ['position' => (int) $position]);
    }

    /**
     * The human display label for one constituent Product Reference — its wine identity: the Product Master NAME
     * (read through the within-Catalog `variant()->master()` chain), the vintage (the WINE attribute set's
     * `vintage_year`, or a localized "NV" non-vintage marker), and the Format name (with its size label where
     * present), e.g. "Romanée-Conti — 2019 — Bottle (750ml)". The ` — ` separator mirrors the sibling Product
     * Reference console's title. A PR has no name of its own, so the label is assembled from its within-Catalog
     * relations; missing pieces are dropped so the label degrades gracefully to whatever identity is known.
     * Renders no `Catalog\Enums` import (the {Models, Actions} surface).
     */
    private static function referenceLabel(ProductReference $reference): string
    {
        $variant = $reference->variant;
        $format = $reference->format;

        $parts = [];

        $master = $variant?->master;
        if ($master !== null) {
            $parts[] = $master->name;
        }

        $vintage = self::vintageLabel($reference);
        if ($vintage !== null) {
            $parts[] = $vintage;
        }

        if ($format !== null) {
            $parts[] = self::formatLabel($format->name, $format->size_label);
        }

        return $parts === [] ? '—' : implode(' — ', $parts);
    }

    /**
     * The vintage fragment of a constituent's label: the WINE attribute set's `vintage_year` as a string, or a
     * localized "NV" marker when the variant is explicitly non-vintage, or null when no vintage information is
     * known (then the label simply omits it). Read off the within-Catalog `variant()->wineAttributes()` 1:1
     * extension.
     */
    private static function vintageLabel(ProductReference $reference): ?string
    {
        $wineAttributes = $reference->variant?->wineAttributes;

        if ($wineAttributes === null) {
            return null;
        }

        if ($wineAttributes->vintage_year !== null) {
            return (string) $wineAttributes->vintage_year;
        }

        if ($wineAttributes->non_vintage) {
            return (string) __('operator_console.composite_sku.non_vintage');
        }

        return null;
    }

    /**
     * Compose a Format's display fragment — its name, suffixed with the size label in parentheses when one is
     * present (e.g. "Bottle (750ml)"); the bare name when no size label is set.
     */
    private static function formatLabel(string $name, ?string $sizeLabel): string
    {
        if ($sizeLabel === null || $sizeLabel === '') {
            return $name;
        }

        return $name.' ('.$sizeLabel.')';
    }

    /**
     * Create-form constituent-Product-Reference options, keyed by `id` → the PR's human label (Master — vintage —
     * format, never a bare `#id`, never a trailing state token), read from Catalog's OWN {@see ProductReference}
     * model (a WITHIN-module reference — a constituent, never a producer, design L6). Creation lists every Product
     * Reference with NO producer filter (producer-agnostic, design D9); the activation-cascade gate (a domain
     * rule) is what blocks activating a Composite SKU whose any constituent is non-active, so the picker need not
     * pre-filter by state. The identity relations are eager-loaded for the label so the option list issues no
     * per-row queries. Renders no `Catalog\Enums` import (the {Models, Actions} surface).
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
                $reference->id => self::referenceLabel($reference),
            ])
            ->all();
    }
}
