<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\RelationManagers;

use App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource;
use App\Modules\Parties\Actions\CreateClub as CreateClubAction;
use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Modules\Parties\Models\Producer;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * ClubsRelationManager — a Producer's operated Clubs, surfaced as an interactive sub-table on the Producer's
 * view page (operator-console UI pass, 2026-06-24). It replaces the standalone Club sidebar console (now hidden
 * from navigation): an operator sees AND creates a Producer's Clubs in the Producer's own context, the operating
 * Producer implied (no Producer picker on the create form).
 *
 * Read columns are reused verbatim from {@see ClubResource::table()}; the row View action links to the
 * still-registered Club view page. Create routes through the Parties {@see CreateClubAction} with the owner
 * Producer id injected — NEVER an Eloquent write (the no-Eloquent-write rule; ADR 2026-06-19) — mirroring the
 * standalone CreateClub page: it constructs the {@see ClubRegistrationFlowType} operand enum (the {Models,
 * Actions, Enums} carve-out, ADR 2026-06-21) and assembles a {@see Money} fee only when both an amount and a
 * currency are supplied (D11). All copy is localized (invariant 12).
 */
class ClubsRelationManager extends RelationManager
{
    protected static string $relationship = 'clubs';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return (string) __('operator_console.relations.clubs');
    }

    /**
     * Authorize create at the console boundary: an authenticated operator may create, with the Parties domain
     * action as the real business-rule guard (there is no per-model Eloquent policy in this app, so the RM's
     * create action is explicitly enabled — mirroring the standalone write-through create page being reachable).
     */
    protected function canCreate(): bool
    {
        return true;
    }

    /**
     * Opt OUT of Filament's default "a relation manager is read-only on a ViewRecord page" rule
     * (RelationManager::isReadOnly() === is_subclass_of(pageClass, ViewRecord::class)). The Producer view IS a
     * ViewRecord, so without this the header CreateAction is DENIED before {@see canCreate()} is consulted — the
     * reason the "New club" button did not appear on the Producer view. We surface it; the Parties CreateClub
     * domain action stays the real write-through guard, and no edit/delete actions are defined, so this enables
     * the create affordance ONLY.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return ClubResource::table($table)
            ->headerActions([
                CreateAction::make()
                    ->label((string) __('operator_console.relations.create_club'))
                    ->modalHeading((string) __('operator_console.relations.create_club'))
                    ->schema([
                        TextInput::make('display_name')
                            ->label((string) __('operator_console.club.fields.display_name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('registration_flow_type')
                            ->label((string) __('operator_console.club.fields.registration_flow_type'))
                            ->options(self::registrationFlowTypeOptions())
                            ->default(ClubRegistrationFlowType::ApplicationWithApproval->value)
                            ->required(),
                        TextInput::make('amount')
                            ->label((string) __('operator_console.club.fields.amount'))
                            ->helperText((string) __('operator_console.club.fields.amount_help'))
                            ->numeric()
                            ->minValue(0)
                            ->prefix((string) __('operator_console.club.fields.amount_prefix')),
                        Select::make('currency')
                            ->label((string) __('operator_console.club.fields.currency'))
                            ->options(self::currencyOptions()),
                        Toggle::make('generates_credit')
                            ->label((string) __('operator_console.club.fields.generates_credit'))
                            ->default(true),
                    ])
                    ->using($this->createClub(...)),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Model $record): string => ClubResource::getUrl('view', ['record' => $record])),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createClub(array $data): Model
    {
        $owner = $this->getOwnerRecord();
        assert($owner instanceof Producer);

        $displayName = $data['display_name'] ?? null;
        $registrationFlowType = $data['registration_flow_type'] ?? null;
        $amount = $data['amount'] ?? null;
        $currency = $data['currency'] ?? null;
        $generatesCredit = $data['generates_credit'] ?? true;

        if (
            ! is_string($displayName)
            || ! is_string($registrationFlowType)
            || ! (is_null($amount) || $amount === '' || is_numeric($amount))
            || ! (is_null($currency) || is_string($currency))
            || ! is_bool($generatesCredit)
        ) {
            throw new InvalidArgumentException('Unexpected Club create payload.');
        }

        $fee = (is_numeric($amount) && is_string($currency) && $currency !== '')
            ? Money::of((int) $amount, Currency::of($currency))
            : null;

        return app(CreateClubAction::class)->handle(
            displayName: $displayName,
            producerId: $owner->id,
            registrationFlowType: ClubRegistrationFlowType::from($registrationFlowType),
            fee: $fee,
            generatesCredit: $generatesCredit,
        );
    }

    /**
     * The registration-flow select options: each launch-selectable enum case keyed by its raw `->value` (the
     * operand the {@see CreateClubAction} reconstructs) → a localized human label off
     * `operator_console.club.registration_flow.*`, so the operator picks "Invitation only" rather than the raw
     * `invitation_only` token. Only the THREE launch channels are offered — the latent `OpenRegistration` is
     * filtered out (canon MVP-DEC-022 / BR-K-Club-6: `open_registration` is carried latent, never selectable at
     * launch; the `Club` model's `saving` guard is the server floor). The per-case label key is the enum `->value`
     * (no `Parties\Enums` symbol leaks into the copy).
     *
     * @return array<string, string>
     */
    private static function registrationFlowTypeOptions(): array
    {
        return collect(ClubRegistrationFlowType::cases())
            ->reject(static fn (ClubRegistrationFlowType $flow): bool => $flow === ClubRegistrationFlowType::OpenRegistration)
            ->mapWithKeys(static fn (ClubRegistrationFlowType $flow): array => [
                $flow->value => (string) __('operator_console.club.registration_flow.'.$flow->value),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function currencyOptions(): array
    {
        return collect(Currency::cases())
            ->mapWithKeys(static fn (Currency $currency): array => [$currency->value => $currency->value])
            ->all();
    }
}
