<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\RelationManagers;

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource;
use App\Modules\Parties\Actions\CreateProducerAgreement as CreateProducerAgreementAction;
use App\Modules\Parties\Enums\SettlementCadence;
use App\Modules\Parties\Models\Producer;
use Carbon\CarbonImmutable;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * ProducerAgreementsRelationManager — a Producer's commercial agreements, surfaced as an interactive sub-table
 * on the Producer's view page (operator-console UI pass, 2026-06-24). It replaces the standalone Producer
 * Agreement sidebar console (now hidden from navigation): an operator sees AND creates a Producer's agreements
 * in the Producer's own context, the Producer implied (no Producer picker), and the OPTIONAL narrowing Club
 * scoped to the Producer's `active` Clubs (BR-K-Agreement-4 / canon MVP-DEC-009).
 *
 * Read columns are reused verbatim from {@see ProducerAgreementResource::table()}; the row View action links to
 * the still-registered agreement view page. Create routes through the Parties {@see CreateProducerAgreementAction}
 * with the owner Producer id injected — NEVER an Eloquent write (the no-Eloquent-write rule; ADR 2026-06-19) —
 * mirroring the standalone CreateProducerAgreement page: it collects ids/dates, the `active`-Club narrowing (blank
 * = a Producer-wide agreement, § 4.6) and the settlement cadence as a Select over the closed {@see SettlementCadence}
 * operand enum (canon MVP-DEC-010/RM-22), both pickers sharing the resource's option helpers. All copy is localized
 * (invariant 12).
 */
class ProducerAgreementsRelationManager extends RelationManager
{
    protected static string $relationship = 'producerAgreements';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return (string) __('operator_console.relations.agreements');
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
     * reason the "New agreement" button did not appear on the Producer view. We surface it; the Parties
     * CreateProducerAgreement domain action stays the real write-through guard, and no edit/delete actions are
     * defined, so this enables the create affordance ONLY.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return ProducerAgreementResource::table($table)
            ->headerActions([
                CreateAction::make()
                    ->label((string) __('operator_console.relations.create_agreement'))
                    ->modalHeading((string) __('operator_console.relations.create_agreement'))
                    ->schema([
                        Select::make('club_id')
                            ->label((string) __('operator_console.producer_agreement.fields.club'))
                            ->options($this->clubOptions()),
                        DatePicker::make('term_start')
                            ->label((string) __('operator_console.producer_agreement.fields.term_start')),
                        DatePicker::make('term_end')
                            ->label((string) __('operator_console.producer_agreement.fields.term_end')),
                        Select::make('settlement_cadence')
                            ->label((string) __('operator_console.producer_agreement.fields.settlement_cadence'))
                            ->options(ProducerAgreementResource::settlementCadenceOptions())
                            ->default(SettlementCadence::default()->value),
                    ])
                    ->using($this->createAgreement(...)),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Model $record): string => ProducerAgreementResource::getUrl('view', ['record' => $record])),
            ]);
    }

    /**
     * The narrowing-Club options scoped to the owner Producer's `active` Clubs (BR-K-Agreement-4 / canon
     * MVP-DEC-009: a per-Club agreement's Club MUST be `active`, so a `sunset`/`closed` Club is not selectable).
     * Delegates to the shared {@see ProducerAgreementResource::activeClubOptions()} with the owner id — the same
     * active-only filter the standalone create form applies, here with the Producer implied by the owner context.
     * A blank selection is a Producer-wide agreement (§ 4.6).
     *
     * @return array<int, string>
     */
    private function clubOptions(): array
    {
        $owner = $this->getOwnerRecord();
        assert($owner instanceof Producer);

        return ProducerAgreementResource::activeClubOptions($owner->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createAgreement(array $data): Model
    {
        $owner = $this->getOwnerRecord();
        assert($owner instanceof Producer);

        $clubId = $data['club_id'] ?? null;
        $termStart = $data['term_start'] ?? null;
        $termEnd = $data['term_end'] ?? null;
        $settlementCadence = $data['settlement_cadence'] ?? null;

        if (
            ! (is_null($clubId) || $clubId === '' || is_numeric($clubId))
            || ! (is_null($termStart) || $termStart === '' || is_string($termStart))
            || ! (is_null($termEnd) || $termEnd === '' || is_string($termEnd))
            || ! (is_null($settlementCadence) || is_string($settlementCadence))
        ) {
            throw new InvalidArgumentException('Unexpected ProducerAgreement create payload.');
        }

        $clubIdValue = is_numeric($clubId) ? (int) $clubId : null;
        $termStartDate = (is_string($termStart) && $termStart !== '') ? CarbonImmutable::parse($termStart) : null;
        $termEndDate = (is_string($termEnd) && $termEnd !== '') ? CarbonImmutable::parse($termEnd) : null;
        $settlementCadenceValue = (is_string($settlementCadence) && $settlementCadence !== '') ? $settlementCadence : null;

        return app(CreateProducerAgreementAction::class)->handle(
            producerId: $owner->id,
            clubId: $clubIdValue,
            termStart: $termStartDate,
            termEnd: $termEndDate,
            settlementCadence: $settlementCadenceValue,
        );
    }
}
