<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource\Pages;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\ProducerAgreement;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ProducerAgreementResource — the operator console's READ-ONLY surface over the Parties ProducerAgreement
 * (operator-console-parties-supply-side, task 7.1; design D2/D6/D7; ADR 2026-06-19 + 2026-06-20 + 2026-06-21).
 * The THIRD console of the Parties (Module K) supply-side, built — like {@see ProducerResource} and
 * {@see ClubResource} — as the non-catalog trait-reuse pattern (the View page assembles its own verb set; see
 * `ProducerAgreementResource\Pages\ViewProducerAgreement`).
 *
 * It extends {@see OperatorConsoleResource} for the read-only conventions every console shares — the
 * `operator_console.<entity>` model labels off {@see i18nKey()}, the semantic state badge/filter helpers and the
 * optimistic-lock `version` metadata. It does NOT call the kit's `lifecycleStateColumn()`: a ProducerAgreement's
 * state attribute is `status` (a `ProducerAgreementStatus`, `draft → active → superseded | terminated`), not the
 * catalog `lifecycle_state` — so this resource supplies its OWN `status` badge column (design D2), rendering the
 * state enum through its cast `->value` and never importing `App\Modules\Parties\Enums\ProducerAgreementStatus`
 * (the {Models} surface for the read path).
 *
 * It read-binds to {@see ProducerAgreement} — the ADR-sanctioned exception, OperatorPanel-only and display-only:
 * the resource queries the model (and its two WITHIN-Parties relations — the required `producer()` and the
 * OPTIONAL `club()` narrowing) for the list table + the view infolist and NEVER writes it. A null `club_id` is a
 * Producer-wide agreement (§ 4.6); {@see clubLabel()} renders the `producer_wide` placeholder for it. An
 * agreement carries only an integer `id` (no human-named column), so {@see getRecordTitle()} composes a human
 * breadcrumb/search title — the Producer name narrowed by the scoped Club — instead of a bare "#id". Every
 * mutation is a separate Filament Action routed through a Parties domain action (the kit's create page + the
 * bespoke view page); there is deliberately NO Edit page and NO Delete/Create default action — the Parties
 * backend ships no agreement update Action, and create lands on a write-through {@see Pages\CreateProducerAgreement}
 * page that takes ids/dates/a free string only (NO operand enum — design D7). The {@see form()} reads Parties' own
 * {@see Producer} and {@see Club} models for its two pickers (a pure within-Parties read — the {Models} carve-out).
 * The no-Eloquent-write PHPStan rule guards the discipline. All user-facing copy is localized through the
 * `operator_console` group (invariant 12).
 */
class ProducerAgreementResource extends OperatorConsoleResource
{
    protected static ?string $model = ProducerAgreement::class;

    protected static ?int $navigationSort = 5;

    protected static function i18nKey(): string
    {
        return 'producer_agreement';
    }

    protected static function navigationGroupCase(): OperatorConsoleNavigationGroup
    {
        return OperatorConsoleNavigationGroup::Parties;
    }

    /**
     * Hidden from the sidebar (operator-console UI pass, 2026-06-24): an agreement is seen and created INSIDE its
     * Producer (see ProducerResource's ProducerAgreementsRelationManager), not as a flat top-level console. The
     * resource stays fully registered — its list / view / create routes remain reachable — only its navigation
     * entry is suppressed.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * The create form (design D6/D7). Collects the inputs the Parties `CreateProducerAgreement` action consumes —
     * the required Producer (a WITHIN-Parties picker; an agreement references EXACTLY ONE Producer, § 4.6), the
     * OPTIONAL narrowing Club (a blank selection is a Producer-wide agreement, § 4.6), the two OPTIONAL term dates
     * and the OPTIONAL free-string settlement cadence (the D19 seam). Unlike the Club create (design D7) it
     * constructs NO operand enum and assembles NO Money: the action takes ids/dates/a string only, so the form
     * stays inside the {Models} read surface (the two pickers query Parties' own models) and the write routes
     * through the action in {@see Pages\CreateProducerAgreement::createViaAction()}, which narrows a blank Club /
     * dates / cadence to null. It deliberately exposes NO `status`: an agreement is born `draft` by the action
     * (design D2), so state never enters as a create input. There is no Edit page — the Parties backend ships no
     * agreement update Action. All labels localized (invariant 12).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('producer_id')
                    ->label((string) __('operator_console.producer_agreement.fields.producer'))
                    ->options(self::producerOptions(...))
                    ->searchable()
                    ->required(),
                Select::make('club_id')
                    ->label((string) __('operator_console.producer_agreement.fields.club'))
                    ->helperText((string) __('operator_console.producer_agreement.fields.club_help'))
                    ->options(self::clubOptions(...))
                    ->searchable(),
                DatePicker::make('term_start')
                    ->label((string) __('operator_console.producer_agreement.fields.term_start')),
                DatePicker::make('term_end')
                    ->label((string) __('operator_console.producer_agreement.fields.term_end')),
                TextInput::make('settlement_cadence')
                    ->label((string) __('operator_console.producer_agreement.fields.settlement_cadence'))
                    ->helperText((string) __('operator_console.producer_agreement.fields.settlement_cadence_help'))
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyConsoleDefaults($table)
            ->columns([
                TextColumn::make('producer')
                    ->label((string) __('operator_console.producer_agreement.columns.producer'))
                    ->weight('bold')
                    ->getStateUsing(fn (ProducerAgreement $record): string => $record->producer->name),
                TextColumn::make('club')
                    ->label((string) __('operator_console.producer_agreement.columns.club'))
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(fn (ProducerAgreement $record): string => self::clubLabel($record)),
                static::statusColumn(),
                TextColumn::make('term_start')
                    ->label((string) __('operator_console.producer_agreement.columns.term_start'))
                    ->date()
                    ->sortable(),
                TextColumn::make('term_end')
                    ->label((string) __('operator_console.producer_agreement.columns.term_end'))
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                static::stateFilter('status', 'columns.status'),
                SelectFilter::make('producer_id')
                    ->label((string) __('operator_console.producer_agreement.columns.producer'))
                    ->options(self::producerOptions(...))
                    ->searchable(),
            ]);
    }

    /**
     * Make the agreement findable from the Cmd/Ctrl+K global search by its human identity — the related
     * Producer's name and the scoped Club's display name (relation-attribute search, since the agreements table
     * itself carries no human-named column). The search hit's label resolves through {@see getRecordTitle()}, so
     * it reads as "Producer — Club", never a bare id (invariant 12).
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['producer.name', 'club.display_name'];
    }

    /**
     * The human record title — composed because a ProducerAgreement carries only an integer `id` (no named
     * column), so the default key-attribute title would read as a bare "#id" in breadcrumbs, global-search hits
     * and the view header. Reads the required Producer's name, narrowed by the scoped Club's display name or the
     * localized `producer_wide` placeholder (a null `club` — § 4.6). Read-only and within-Parties; the
     * no-Eloquent-write rule polices writes.
     */
    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof ProducerAgreement) {
            return null;
        }

        return $record->producer->name.' — '.self::clubLabel($record);
    }

    /**
     * The read-only view (design D2). Grouped into premium, icon-headed sections mirroring the catalog template —
     * Parties (the required Producer + the scoped Club, the latter falling back to the Producer-wide placeholder),
     * Status & terms (the `status` rendered as the SAME semantic colored badge the list carries through
     * {@see badgedStateEntry()}, never plain text, alongside the term dates and the settlement-cadence seam), and
     * a collapsed Metadata section for the optimistic-lock `version`. Every entry is display-only; the Producer /
     * Club names resolve through the within-Parties relations. All copy localized (invariant 12).
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make((string) __('operator_console.producer_agreement.sections.parties'))
                    ->icon('heroicon-o-building-storefront')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('producer')
                            ->label((string) __('operator_console.producer_agreement.columns.producer'))
                            ->weight('bold')
                            ->getStateUsing(fn (ProducerAgreement $record): string => $record->producer->name),
                        TextEntry::make('club')
                            ->label((string) __('operator_console.producer_agreement.fields.club'))
                            ->getStateUsing(fn (ProducerAgreement $record): string => self::clubLabel($record)),
                    ]),
                Section::make((string) __('operator_console.producer_agreement.sections.terms'))
                    ->icon('heroicon-o-document-check')
                    ->columns(2)
                    ->schema([
                        static::badgedStateEntry('status', 'columns.status'),
                        TextEntry::make('settlement_cadence')
                            ->label((string) __('operator_console.producer_agreement.fields.settlement_cadence'))
                            ->placeholder((string) __('operator_console.producer_agreement.not_set')),
                        TextEntry::make('term_start')
                            ->label((string) __('operator_console.producer_agreement.columns.term_start'))
                            ->date()
                            ->placeholder((string) __('operator_console.producer_agreement.not_set')),
                        TextEntry::make('term_end')
                            ->label((string) __('operator_console.producer_agreement.columns.term_end'))
                            ->date()
                            ->placeholder((string) __('operator_console.producer_agreement.not_set')),
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
            'index' => Pages\ListProducerAgreements::route('/'),
            'create' => Pages\CreateProducerAgreement::route('/create'),
            'view' => Pages\ViewProducerAgreement::route('/{record}'),
        ];
    }

    /**
     * The ProducerAgreement status badge column (design D2). The state lives in `status` (a
     * `ProducerAgreementStatus`), not the catalog `lifecycle_state`, so the kit's `lifecycleStateColumn()` does
     * not fit — this reads the real attribute and renders it through its BackedEnum cast `->value` (e.g. `draft`),
     * avoiding any `Parties\Enums` import (the {Models} read surface). `getAttribute()` is a read; the
     * no-Eloquent-write rule polices writes. `status` is a real DB column, so the badge is sortable.
     */
    protected static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label((string) __('operator_console.producer_agreement.columns.status'))
            ->badge()
            ->sortable()
            ->color(fn (string $state): string => static::stateBadgeColor($state))
            ->icon(fn (string $state): ?string => static::stateBadgeIcon($state))
            ->getStateUsing(fn (Model $record): string => self::statusValue($record));
    }

    /**
     * Render the `status` state enum through its cast `->value` — the import-free read surface shared by the badge
     * column (the {Models} surface, never a `Parties\Enums` import).
     */
    private static function statusValue(Model $record): string
    {
        $state = $record->getAttribute('status');

        return $state instanceof BackedEnum ? (string) $state->value : '';
    }

    /**
     * The scoped Club's display name, or the localized `producer_wide` placeholder when the agreement is
     * Producer-wide (a null `club` — § 4.6). `club` is the OPTIONAL within-Parties `belongsTo`
     * ({@see ProducerAgreement::club()}); the local + null-check narrows the `Club|null` to `Club` before the
     * `display_name` read (the nullsafe `?->` form is rejected because Larastan types the nullsafe operand off
     * the non-null relation generic). Read-only — the no-Eloquent-write rule polices writes.
     */
    private static function clubLabel(ProducerAgreement $record): string
    {
        $club = $record->club;

        return $club === null
            ? (string) __('operator_console.producer_agreement.producer_wide')
            : $club->display_name;
    }

    /**
     * Create-form + filter Producer options, keyed by `producer_id` → the Producer's human NAME, read from
     * Parties' OWN {@see Producer} model (a WITHIN-Parties reference — an agreement references EXACTLY ONE
     * Producer, § 4.6, never a cross-module join). Creation lists every Producer; the action's
     * `MissingAgreementProducer` pre-check (and the within-module FK) is the real guard, so the picker need not
     * pre-filter. A pure read — the no-Eloquent-write rule polices writes only — and `Producer` is within the
     * {Models} carve-out.
     *
     * @return array<int, string>
     */
    private static function producerOptions(): array
    {
        return Producer::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(static fn (Producer $producer): array => [
                $producer->id => $producer->name,
            ])
            ->all();
    }

    /**
     * Create-form narrowing-Club options, keyed by `club_id` → the Club's human `display_name`, read from
     * Parties' OWN {@see Club} model (a WITHIN-Parties reference). The Club is OPTIONAL — a blank selection is a
     * Producer-wide agreement (§ 4.6), so this Select is not `required`; {@see Pages\CreateProducerAgreement}
     * narrows a blank value to a null `club_id`. A pure read within the {Models} carve-out.
     *
     * @return array<int, string>
     */
    private static function clubOptions(): array
    {
        return Club::query()
            ->orderBy('display_name')
            ->get()
            ->mapWithKeys(static fn (Club $club): array => [
                $club->id => $club->display_name,
            ])
            ->all();
    }
}
