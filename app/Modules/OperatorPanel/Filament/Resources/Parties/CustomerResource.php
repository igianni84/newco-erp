<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\RelationManagers\MembershipsRelationManager;
use App\Modules\Parties\Models\Customer;
use App\Platform\I18n\SupportedLocale;
use App\Platform\Money\Currency;
use BackedEnum;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * CustomerResource — the operator console's READ-ONLY surface over the Parties Customer
 * (operator-console-parties-customer, task 1.1; design D1/D2/D7; ADR 2026-06-19 + 2026-06-20 + 2026-06-21). The
 * FIRST DEMAND-SIDE Parties (Module K) console — the "least kit-shaped surface" the supply-side trilogy
 * deferred to — built, like its siblings, on the shared kit as the non-catalog trait-reuse pattern (the View
 * page assembles its own verb set; see `CustomerResource\Pages\ViewCustomer`).
 *
 * It extends {@see OperatorConsoleResource} for the read-only conventions every console shares — the
 * `operator_console.<entity>` model labels off {@see i18nKey()} and the optimistic-lock `version` column helper.
 * It does NOT call the kit's `lifecycleStateColumn()`: the Customer carries THREE orthogonal lifecycles on one
 * record — the status FSM (`status`, a `CustomerStatus`), the KYC lifecycle (`kyc_status`, a nullable
 * `KycStatus`) and the sanctions lifecycle (`sanctions_status`, a nullable `SanctionsStatus`) — so this resource
 * supplies its OWN three badge columns, each rendered through its BackedEnum cast `->value` and never by
 * importing `App\Modules\Parties\Enums\*` (the {Models, Actions} read surface — design D2). Alongside them the
 * list/infolist surface the co-provisioned Account's `status` and the Customer's Club-membership Profiles, both
 * read-only WITHIN-Parties reads (the boundary law forbids only CROSS-module relations).
 *
 * It read-binds to {@see Customer} — the ADR-sanctioned exception, OperatorPanel-only and display-only: the
 * resource queries the model (and its within-Parties `account()` / `profiles()` relations) for the list table +
 * the view infolist and NEVER writes it. Every mutation is a separate Filament Action routed through a Parties
 * domain action (the kit's create page + the bespoke view page); there is deliberately NO Edit page and NO
 * Delete/Create default action — create lands on a write-through {@see Pages\CreateCustomer} page reached by a
 * list-header navigation link, and the status verbs live on {@see Pages\ViewCustomer}. The no-Eloquent-write
 * PHPStan rule guards the discipline. All user-facing copy is localized through the `operator_console` group
 * (invariant 12).
 */
class CustomerResource extends OperatorConsoleResource
{
    protected static ?string $model = Customer::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    protected static function i18nKey(): string
    {
        return 'customer';
    }

    protected static function navigationGroupCase(): OperatorConsoleNavigationGroup
    {
        return OperatorConsoleNavigationGroup::Parties;
    }

    /**
     * The create form (design D5/D6/D7). Collects the inputs the Parties `CreateCustomer` action consumes — the
     * required email (email-validated) and name, the required preferred-currency / preferred-locale preferences
     * (the ISO 4217 / launch-locale Selects), and the OPTIONAL phone and date_of_birth. It deliberately exposes
     * NO `status`: a Customer is born `pending` by the action and advances only through the ViewCustomer status
     * verbs (task 3.1), never as a create-form input (design D5). The form only COLLECTS; the write routes
     * through the action in {@see Pages\CreateCustomer::createViaAction()}, which constructs the PLATFORM
     * `Currency::of()` / `SupportedLocale::from()` operands from the selected codes — no `Parties\Enums` import,
     * so the boundary needs no widening (design D6). There is no Edit page — the Parties backend ships no Customer
     * profile-update Action. All labels localized (invariant 12).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label((string) __('operator_console.customer.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('name')
                    ->label((string) __('operator_console.customer.fields.name'))
                    ->required()
                    ->maxLength(255),
                Select::make('preferred_currency')
                    ->label((string) __('operator_console.customer.fields.preferred_currency'))
                    ->options(self::currencyOptions(...))
                    ->required(),
                Select::make('preferred_locale')
                    ->label((string) __('operator_console.customer.fields.preferred_locale'))
                    ->options(self::localeOptions(...))
                    ->required(),
                TextInput::make('phone')
                    ->label((string) __('operator_console.customer.fields.phone'))
                    ->maxLength(255),
                DatePicker::make('date_of_birth')
                    ->label((string) __('operator_console.customer.fields.date_of_birth')),
            ]);
    }

    /**
     * The read list — the demand-side Customer registry. The human-identity columns `name` + `email` are both
     * searchable (the operator finds a Customer by either) and sortable. The four status axes each render as the
     * SAME semantic colored + iconed badge the rest of the console uses ({@see stateBadgeColor()} /
     * {@see stateBadgeIcon()}): `status` / `kyc_status` / `sanctions_status` are real Customer columns (so they
     * sort and filter); `account_status` is the co-provisioned Account's state read off the within-Parties
     * `account()` relation (NOT a Customer column — so it neither sorts nor filters), and `profiles` is the
     * Club-membership COUNT off the `profiles()` relation (likewise relation-derived). The three filters cover the
     * three real status columns via the kit's {@see stateFilter()} (distinct-token select — no `Parties\Enums`
     * import; design D2). Branded list defaults via {@see applyConsoleDefaults()}; no mutating row/bulk action —
     * the surface is read-only (the write verbs live on the view/create pages).
     */
    public static function table(Table $table): Table
    {
        return static::applyConsoleDefaults($table)
            ->columns([
                TextColumn::make('name')
                    ->label((string) __('operator_console.customer.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label((string) __('operator_console.customer.columns.email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label((string) __('operator_console.customer.columns.status'))
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => static::stateBadgeColor($state))
                    ->icon(fn (string $state): ?string => static::stateBadgeIcon($state))
                    ->getStateUsing(self::enumBadgeState('status')),
                TextColumn::make('kyc_status')
                    ->label((string) __('operator_console.customer.columns.kyc_status'))
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => static::stateBadgeColor($state))
                    ->icon(fn (string $state): ?string => static::stateBadgeIcon($state))
                    ->getStateUsing(self::enumBadgeState('kyc_status')),
                TextColumn::make('sanctions_status')
                    ->label((string) __('operator_console.customer.columns.sanctions_status'))
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => static::stateBadgeColor($state))
                    ->icon(fn (string $state): ?string => static::stateBadgeIcon($state))
                    ->getStateUsing(self::enumBadgeState('sanctions_status')),
                TextColumn::make('account_status')
                    ->label((string) __('operator_console.customer.columns.account_status'))
                    ->badge()
                    ->color(fn (string $state): string => static::stateBadgeColor($state))
                    ->icon(fn (string $state): ?string => static::stateBadgeIcon($state))
                    ->getStateUsing(self::accountStatusState()),
                TextColumn::make('profiles')
                    ->label((string) __('operator_console.customer.columns.profiles'))
                    ->badge()
                    ->getStateUsing(fn (Customer $record): int => $record->profiles->count()),
            ])
            ->filters([
                static::stateFilter('status', 'columns.status'),
                static::stateFilter('kyc_status', 'columns.kyc_status'),
                static::stateFilter('sanctions_status', 'columns.sanctions_status'),
            ]);
    }

    /**
     * Make the Customer findable from the Cmd/Ctrl+K global search by the two human-identity columns — the name
     * and the email (the operator knows a Customer by either). Pairs with {@see $recordTitleAttribute} = 'name'.
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    /**
     * The read-only view (design D2). Grouped into premium, icon-headed Sections mirroring Product Master: Identity
     * (the personal-data attributes — name / email / phone / date of birth), Preferences (the ISO 4217 currency +
     * launch-locale preferences), Compliance (the two orthogonal compliance axes `kyc_status` + `sanctions_status`,
     * each rendered through the shared {@see badgedStateEntry()} so the detail shows the SAME semantic colored badge
     * the list carries — never plain text), State (the Customer status FSM + the co-provisioned Account's state),
     * closing with the collapsed {@see metadataSection()} for the optimistic-lock `version`. The Account state is a
     * within-Parties read off the {@see Customer::account()} relation (NOT a Customer column, so it uses a bespoke
     * badge entry, not `badgedStateEntry()` which reads a column attribute). All copy localized (invariant 12).
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make((string) __('operator_console.customer.sections.identity'))
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label((string) __('operator_console.customer.columns.name'))
                            ->weight('bold'),
                        TextEntry::make('email')
                            ->label((string) __('operator_console.customer.columns.email'))
                            ->copyable(),
                        TextEntry::make('phone')
                            ->label((string) __('operator_console.customer.fields.phone'))
                            ->placeholder((string) __('operator_console.placeholder_none')),
                        TextEntry::make('date_of_birth')
                            ->label((string) __('operator_console.customer.fields.date_of_birth'))
                            ->date()
                            ->placeholder((string) __('operator_console.placeholder_none')),
                    ]),
                Section::make((string) __('operator_console.customer.sections.preferences'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('preferred_currency')
                            ->label((string) __('operator_console.customer.fields.preferred_currency'))
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('preferred_locale')
                            ->label((string) __('operator_console.customer.fields.preferred_locale'))
                            ->badge()
                            ->color('gray'),
                    ]),
                Section::make((string) __('operator_console.customer.sections.compliance'))
                    ->icon('heroicon-o-shield-check')
                    ->columns(2)
                    ->schema([
                        static::badgedStateEntry('kyc_status'),
                        static::badgedStateEntry('sanctions_status'),
                    ]),
                Section::make((string) __('operator_console.customer.sections.state'))
                    ->icon('heroicon-o-flag')
                    ->columns(2)
                    ->schema([
                        static::badgedStateEntry('status'),
                        TextEntry::make('account_status')
                            ->label((string) __('operator_console.customer.columns.account_status'))
                            ->badge()
                            ->color(fn (string $state): string => static::stateBadgeColor($state))
                            ->icon(fn (string $state): ?string => static::stateBadgeIcon($state))
                            ->getStateUsing(self::accountStatusState()),
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
    }

    /**
     * The Customer's Club memberships (Profiles), surfaced READ-ONLY as a sub-table on the view page
     * (operator-console UI pass, 2026-06-24) — the per-Customer view of the Netflix-style membership model
     * (one Profile per Club). Creation/lifecycle live on the cross-Customer approval queue (ProfileResource),
     * not here. ViewRecord renders relation managers below the infolist.
     *
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            MembershipsRelationManager::class,
        ];
    }

    /**
     * The shared read resolver for one of the Customer's enum-cast badge columns — `status` (a `CustomerStatus`)
     * or the two nullable compliance axes `kyc_status` / `sanctions_status`. The state is read off the record and
     * rendered through its BackedEnum cast `->value` (e.g. `active`, `verified`, `passed`), or the empty string
     * when a nullable column is NULL (a never-screened Customer) — never crashing on a missing enum and never
     * importing `App\Modules\Parties\Enums\*` (the {Models, Actions} read surface — design D2). `getAttribute()`
     * returns mixed, so the `instanceof BackedEnum` test is meaningful (not always-true); it is a read — the
     * no-Eloquent-write rule polices writes only.
     *
     * @return Closure(Model): string
     */
    private static function enumBadgeState(string $attribute): Closure
    {
        return function (Model $record) use ($attribute): string {
            $state = $record->getAttribute($attribute);

            return $state instanceof BackedEnum ? (string) $state->value : '';
        };
    }

    /**
     * The read resolver for the co-provisioned Account's `status` badge — a WITHIN-Parties read off the
     * {@see Customer::account()} relation (no cross-module access, no extra import). The relation is nullable
     * (`@property-read Account|null` — a factory-seeded Customer has no Account; a console-created one always
     * does), so a local + null-check renders the empty string when absent and the `AccountStatus` cast `->value`
     * otherwise — the phpstan-clean nullable-relation shape (a nullsafe read off the non-null relation generic
     * would red `nullsafe.neverNull`).
     *
     * @return Closure(Customer): string
     */
    private static function accountStatusState(): Closure
    {
        return function (Customer $record): string {
            $account = $record->account;

            return $account === null ? '' : $account->status->value;
        };
    }

    /**
     * Create-form preferred-currency options, keyed by the ISO 4217 code → the same code as its label, driven off
     * the Platform {@see Currency} enum (the launch set is the five DEC-037 currencies). `Currency` is an
     * `App\Platform` type, freely importable (not cross-module — design D6); {@see Pages\CreateCustomer}
     * constructs the `Currency::of()` operand from the selected code. The code is domain data (like a read
     * column), not UI chrome, so no per-value i18n key is introduced — only the Select's own `label` is localized.
     *
     * @return array<string, string>
     */
    private static function currencyOptions(): array
    {
        return collect(Currency::cases())
            ->mapWithKeys(static fn (Currency $currency): array => [$currency->value => $currency->value])
            ->all();
    }

    /**
     * Create-form preferred-locale options, keyed by the locale code → the same code as its label, driven off the
     * Platform {@see SupportedLocale} enum (the six DEC-031 launch locales). `SupportedLocale` is an `App\Platform`
     * type, freely importable (not cross-module — design D6); {@see Pages\CreateCustomer} constructs the
     * `SupportedLocale::from()` operand from the selected code. Domain data, not UI chrome — only the Select's own
     * `label` is localized.
     *
     * @return array<string, string>
     */
    private static function localeOptions(): array
    {
        return collect(SupportedLocale::cases())
            ->mapWithKeys(static fn (SupportedLocale $locale): array => [$locale->value => $locale->value])
            ->all();
    }
}
