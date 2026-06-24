<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\RelationManagers;

use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource;
use App\Modules\Parties\Actions\CreateProducerAgreement as CreateProducerAgreementAction;
use App\Modules\Parties\Models\Producer;
use Carbon\CarbonImmutable;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * ProducerAgreementsRelationManager — a Producer's commercial agreements, surfaced as an interactive sub-table
 * on the Producer's view page (operator-console UI pass, 2026-06-24). It replaces the standalone Producer
 * Agreement sidebar console (now hidden from navigation): an operator sees AND creates a Producer's agreements
 * in the Producer's own context, the Producer implied (no Producer picker), and the OPTIONAL narrowing Club
 * scoped to the Producer's OWN Clubs.
 *
 * Read columns are reused verbatim from {@see ProducerAgreementResource::table()}; the row View action links to
 * the still-registered agreement view page. Create routes through the Parties {@see CreateProducerAgreementAction}
 * with the owner Producer id injected — NEVER an Eloquent write (the no-Eloquent-write rule; ADR 2026-06-19) —
 * mirroring the standalone CreateProducerAgreement page (ids/dates/free string only, a blank Club narrowed to a
 * Producer-wide agreement — § 4.6). All copy is localized (invariant 12).
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
                        TextInput::make('settlement_cadence')
                            ->label((string) __('operator_console.producer_agreement.fields.settlement_cadence'))
                            ->maxLength(255),
                    ])
                    ->using($this->createAgreement(...)),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Model $record): string => ProducerAgreementResource::getUrl('view', ['record' => $record])),
            ]);
    }

    /**
     * The narrowing-Club options scoped to the owner Producer's OWN Clubs (a within-Parties read off the owner
     * relation). A blank selection is a Producer-wide agreement (§ 4.6).
     *
     * @return array<int, string>
     */
    private function clubOptions(): array
    {
        $owner = $this->getOwnerRecord();
        assert($owner instanceof Producer);

        $options = [];
        foreach ($owner->clubs as $club) {
            $options[$club->id] = '#'.$club->id.' · '.$club->display_name;
        }

        return $options;
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
