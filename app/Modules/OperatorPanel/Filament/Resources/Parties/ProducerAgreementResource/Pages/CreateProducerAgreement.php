<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleCreateRecord;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerAgreementResource;
use App\Modules\Parties\Actions\CreateProducerAgreement as CreateProducerAgreementAction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The write-through Create page for a ProducerAgreement (operator-console-parties-supply-side, task 7.2/8.1;
 * design D6/D7; ADR 2026-06-19 + 2026-06-20; spec — Operator creates a ProducerAgreement through the console).
 *
 * The console NEVER saves the model directly: the kit base's `handleRecordCreation()` delegates to
 * {@see createViaAction()} here, which routes the form data into the Parties domain action
 * {@see CreateProducerAgreementAction} and returns the new `ProducerAgreement` (born `draft`, recording
 * `ProducerAgreementCreated`). Filament's default `new Model($data); $record->save()` stays fully overridden by
 * the base — there is no `$model->save()` here (the no-Eloquent-write PHPStan rule guards it). The actor envelope
 * (`actor_role: newco_ops` + the operator id) is resolved by the action through the platform `ActorContext` seam
 * off the authenticated `operator` guard — the page constructs none.
 *
 * Unlike the Club create (design D7), this surface constructs NO operand enum: the action takes only the required
 * Producer id, the OPTIONAL Club id (blank = a Producer-wide agreement — § 4.6), the OPTIONAL term dates and the
 * OPTIONAL free-string settlement cadence — so {@see createViaAction()} narrows ids/dates/string at the boundary
 * and stays inside the {Models, Actions} carve-out (no `Parties\Enums` import). The agreement's `status` is born
 * `draft` by the action, so the form exposes no status input.
 *
 * The page/action class-name collision (this page is `CreateProducerAgreement`, the domain action is also
 * `CreateProducerAgreement`) is resolved by aliasing the action import to `CreateProducerAgreementAction` (design
 * D6, mirrors `CreateClub`). A non-existent Producer is rejected by the action with a localized
 * `MissingAgreementProducer` (a `RuntimeException`), surfaced by the base's catch on {@see createRejectionField()}
 * (`producer_id`) as a form error rather than a 500.
 */
class CreateProducerAgreement extends OperatorConsoleCreateRecord
{
    protected static string $resource = ProducerAgreementResource::class;

    protected function createRejectionField(): string
    {
        return 'producer_id';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createViaAction(array $data): Model
    {
        // Filament types the post-validation form state as array<string, mixed>; narrow each value to the
        // Parties action's typed contract at the boundary. The required producer_id makes the happy path
        // well-formed; the club_id, the two term dates and the settlement cadence are all optional (blank →
        // null). InvalidArgumentException is a LogicException, so it propagates past the base's RuntimeException
        // catch — a programming bug, not a form error.
        $producerId = $data['producer_id'] ?? null;
        $clubId = $data['club_id'] ?? null;
        $termStart = $data['term_start'] ?? null;
        $termEnd = $data['term_end'] ?? null;
        $settlementCadence = $data['settlement_cadence'] ?? null;

        if (
            ! is_numeric($producerId)
            || ! (is_null($clubId) || $clubId === '' || is_numeric($clubId))
            || ! (is_null($termStart) || $termStart === '' || is_string($termStart))
            || ! (is_null($termEnd) || $termEnd === '' || is_string($termEnd))
            || ! (is_null($settlementCadence) || is_string($settlementCadence))
        ) {
            throw new InvalidArgumentException('Unexpected ProducerAgreement create payload.');
        }

        // Optional inputs collapse blank → null. Club narrowing is null for a Producer-wide agreement (§ 4.6);
        // the term DatePicker values dehydrate as 'Y-m-d' strings, parsed into the action's ?CarbonInterface;
        // the settlement cadence is a free string at launch (the D19 seam).
        $clubIdValue = is_numeric($clubId) ? (int) $clubId : null;
        $termStartDate = (is_string($termStart) && $termStart !== '') ? CarbonImmutable::parse($termStart) : null;
        $termEndDate = (is_string($termEnd) && $termEnd !== '') ? CarbonImmutable::parse($termEnd) : null;
        $settlementCadenceValue = (is_string($settlementCadence) && $settlementCadence !== '') ? $settlementCadence : null;

        return app(CreateProducerAgreementAction::class)->handle(
            producerId: (int) $producerId,
            clubId: $clubIdValue,
            termStart: $termStartDate,
            termEnd: $termEndDate,
            settlementCadence: $settlementCadenceValue,
        );
    }
}
