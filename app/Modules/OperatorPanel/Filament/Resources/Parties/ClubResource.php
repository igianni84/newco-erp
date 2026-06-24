<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource\Pages;
use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Platform\Money\Currency;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ClubResource — the operator console's READ-ONLY surface over the Parties Club (operator-console-parties-
 * supply-side, task 2.1; design D2/D6/D7; ADR 2026-06-19 + 2026-06-20 + 2026-06-21). The SECOND console of the
 * Parties (Module K) supply-side, built — like {@see ProducerResource} — as the non-catalog trait-reuse pattern
 * (the View page assembles its own verb set; see `ClubResource\Pages\ViewClub`).
 *
 * It extends {@see OperatorConsoleResource} for the read-only conventions every console shares — the
 * `operator_console.<entity>` model labels off {@see i18nKey()} and the optimistic-lock `version` column helper.
 * It does NOT call the kit's `lifecycleStateColumn()`: a Club's state attribute is `status` (a `ClubStatus`,
 * `active → sunset → closed`), not the catalog `lifecycle_state` — so this resource supplies its OWN `status`
 * badge column (design D2), rendering the state enum through its cast `->value` and never importing
 * `App\Modules\Parties\Enums\ClubStatus` (the {Models} surface for the read path). `registration_flow_type`
 * (a `ClubRegistrationFlowType`, a fixed per-Club classifier, not a lifecycle state) renders the same way.
 *
 * It read-binds to {@see Club} — the ADR-sanctioned exception, OperatorPanel-only and display-only: the resource
 * queries the model (and its WITHIN-Parties `producer()` relation) for the list table + the view infolist and
 * NEVER writes it. Every mutation is a separate Filament Action routed through a Parties domain action (the kit's
 * create page + the bespoke view page); there is deliberately NO Edit page and NO Delete/Create default action —
 * the Parties backend ships no Club update Action (post-creation field edits are out of scope, proposal
 * slice-boundary), and create lands on a write-through {@see Pages\CreateClub} page that constructs the
 * `ClubRegistrationFlowType` OPERAND enum (admitted by the {Models, Actions, Enums} carve-out — ADR 2026-06-21).
 * The no-Eloquent-write PHPStan rule guards the discipline. All user-facing copy is localized through the
 * `operator_console` group (invariant 12).
 */
class ClubResource extends OperatorConsoleResource
{
    protected static ?string $model = Club::class;

    protected static ?string $recordTitleAttribute = 'display_name';

    protected static ?int $navigationSort = 3;

    protected static function i18nKey(): string
    {
        return 'club';
    }

    protected static function navigationGroupCase(): OperatorConsoleNavigationGroup
    {
        return OperatorConsoleNavigationGroup::Parties;
    }

    /**
     * The create form (design D6/D7/D11). Collects the inputs the Parties `CreateClub` action consumes — the
     * required display_name, the operating Producer (a WITHIN-Parties picker; a Club belongs to exactly one
     * Producer, BR-K-Club-1), the required `registration_flow_type` classifier, the OPTIONAL fee (an amount in
     * integer minor units + an ISO 4217 currency, assembled into a `Money` only when both are present — D11),
     * and the two single-tier flags. It deliberately exposes NO `status`: a Club is born `active` by the action,
     * with no activate verb (design D9), so state never enters as a create input. The form only COLLECTS; the
     * write routes through the action in {@see Pages\CreateClub::createViaAction()}, which constructs the
     * `ClubRegistrationFlowType` operand enum from the selected value (the {Models, Actions, Enums} carve-out —
     * ADR 2026-06-21). There is no Edit page — the Parties backend ships no Club update Action. All labels
     * localized (invariant 12).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('display_name')
                    ->label((string) __('operator_console.club.fields.display_name'))
                    ->required()
                    ->maxLength(255),
                Select::make('producer_id')
                    ->label((string) __('operator_console.club.fields.producer'))
                    ->options(self::producerOptions(...))
                    ->required(),
                Select::make('registration_flow_type')
                    ->label((string) __('operator_console.club.fields.registration_flow_type'))
                    ->options(self::registrationFlowTypeOptions(...))
                    ->required(),
                TextInput::make('amount')
                    ->label((string) __('operator_console.club.fields.amount'))
                    ->numeric(),
                Select::make('currency')
                    ->label((string) __('operator_console.club.fields.currency'))
                    ->options(self::currencyOptions(...)),
                Toggle::make('generates_credit')
                    ->label((string) __('operator_console.club.fields.generates_credit'))
                    ->default(true),
                Toggle::make('invite_only')
                    ->label((string) __('operator_console.club.fields.invite_only'))
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label((string) __('operator_console.club.columns.display_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('producer')
                    ->label((string) __('operator_console.club.columns.producer'))
                    ->getStateUsing(fn (Club $record): string => $record->producer->name),
                static::registrationFlowTypeColumn(),
                static::statusColumn(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('display_name')
                    ->label((string) __('operator_console.club.columns.display_name')),
                TextEntry::make('producer')
                    ->label((string) __('operator_console.club.columns.producer'))
                    ->getStateUsing(fn (Club $record): string => $record->producer->name),
                TextEntry::make('registration_flow_type')
                    ->label((string) __('operator_console.club.columns.registration_flow_type'))
                    ->getStateUsing(function (Model $record): string {
                        $state = $record->getAttribute('registration_flow_type');

                        return $state instanceof BackedEnum ? (string) $state->value : '';
                    }),
                TextEntry::make('fee')
                    ->label((string) __('operator_console.club.fields.fee'))
                    ->getStateUsing(function (Club $record): ?string {
                        $fee = $record->fee;

                        return $fee === null ? null : $fee->minorUnits.' '.$fee->currency->value;
                    }),
                IconEntry::make('generates_credit')
                    ->label((string) __('operator_console.club.fields.generates_credit'))
                    ->boolean(),
                IconEntry::make('invite_only')
                    ->label((string) __('operator_console.club.fields.invite_only'))
                    ->boolean(),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClubs::route('/'),
            'create' => Pages\CreateClub::route('/create'),
            'view' => Pages\ViewClub::route('/{record}'),
        ];
    }

    /**
     * The Club status badge column (design D2). A Club's state lives in `status` (a `ClubStatus`), not the
     * catalog `lifecycle_state`, so the kit's `lifecycleStateColumn()` does not fit — this reads the real
     * attribute and renders it through its BackedEnum cast `->value` (e.g. `active`), avoiding any `Parties\Enums`
     * import (the {Models} read surface). `getAttribute()` is a read; the no-Eloquent-write rule polices writes.
     */
    protected static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label((string) __('operator_console.club.columns.status'))
            ->badge()
            ->color(fn (string $state): string => static::stateBadgeColor($state))
            ->icon(fn (string $state): ?string => static::stateBadgeIcon($state))
            ->getStateUsing(function (Model $record): string {
                $state = $record->getAttribute('status');

                return $state instanceof BackedEnum ? (string) $state->value : '';
            });
    }

    /**
     * The Club registration-flow classifier column. `registration_flow_type` is a `ClubRegistrationFlowType` —
     * a fixed per-Club configuration attribute (NOT a lifecycle state) — rendered through its cast `->value`
     * (e.g. `invitation_only`), import-free exactly like {@see statusColumn()}. The create surface constructs
     * this enum as an operand (Pages\CreateClub); the read column never imports it.
     */
    protected static function registrationFlowTypeColumn(): TextColumn
    {
        return TextColumn::make('registration_flow_type')
            ->label((string) __('operator_console.club.columns.registration_flow_type'))
            ->getStateUsing(function (Model $record): string {
                $state = $record->getAttribute('registration_flow_type');

                return $state instanceof BackedEnum ? (string) $state->value : '';
            });
    }

    /**
     * Create-form operating-Producer options, keyed by `producer_id` → a `#id · name` label, read from Parties'
     * OWN {@see Producer} model (a WITHIN-Parties reference — a Club belongs to exactly one operating Producer,
     * BR-K-Club-1, never a cross-module join). Creation lists every Producer; the action's `MissingClubProducer`
     * pre-check (and the within-module FK) is the real guard, so the picker need not pre-filter. `getStateUsing`
     * aside, this is a pure read — the no-Eloquent-write rule polices writes only — and `Producer` is within the
     * {Models} carve-out.
     *
     * @return array<int, string>
     */
    private static function producerOptions(): array
    {
        return Producer::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(static fn (Producer $producer): array => [
                $producer->id => '#'.$producer->id.' · '.$producer->name,
            ])
            ->all();
    }

    /**
     * Create-form registration-flow options, keyed by the {@see ClubRegistrationFlowType} backing value → the
     * same token as its label (the per-Club classifier is a fixed enum; the four flows are the full launch
     * domain). Driven off the OPERAND enum (design D7) — the import the {Models, Actions, Enums} carve-out admits
     * for OperatorPanel (ADR 2026-06-21); `CreateClub::createViaAction` constructs the same enum from the
     * selected value. The token is domain data (like the read column), not UI chrome, so no per-value i18n key is
     * introduced — only the Select's own `label` is localized.
     *
     * @return array<string, string>
     */
    private static function registrationFlowTypeOptions(): array
    {
        return collect(ClubRegistrationFlowType::cases())
            ->mapWithKeys(static fn (ClubRegistrationFlowType $flow): array => [$flow->value => $flow->value])
            ->all();
    }

    /**
     * Create-form fee-currency options, keyed by the ISO 4217 code → the same code as its label, driven off the
     * Platform {@see Currency} enum (the launch set is the five DEC-037 currencies). `Currency` is an
     * `App\Platform` type, freely importable (not cross-module). The fee is optional, so this Select is not
     * `required`; `CreateClub` assembles a `Money` only when BOTH an amount and a currency are supplied (D11).
     *
     * @return array<string, string>
     */
    private static function currencyOptions(): array
    {
        return collect(Currency::cases())
            ->mapWithKeys(static fn (Currency $currency): array => [$currency->value => $currency->value])
            ->all();
    }
}
