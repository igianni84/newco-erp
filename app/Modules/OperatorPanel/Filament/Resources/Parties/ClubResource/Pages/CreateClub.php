<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleCreateRecord;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource;
use App\Modules\Parties\Actions\CreateClub as CreateClubAction;
use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The write-through Create page for a Club (operator-console-parties-supply-side, task 3.1; design D6/D7/D11;
 * ADR 2026-06-19 + 2026-06-20 + 2026-06-21; spec — Operator creates a Club through the console).
 *
 * The console NEVER saves the model directly: the kit base's `handleRecordCreation()` delegates to
 * {@see createViaAction()} here, which routes the form data into the Parties domain action {@see CreateClubAction}
 * and returns the new `Club` (born `active`, recording `ClubCreated`). Filament's default `new Model($data);
 * $record->save()` stays fully overridden by the base — there is no `$model->save()` here (the no-Eloquent-write
 * PHPStan rule guards it). The actor envelope (`actor_role: newco_ops` + the operator id) is resolved by the
 * action through the platform `ActorContext` seam off the authenticated `operator` guard — the page constructs
 * none.
 *
 * This page is the change's one architectural seam (design D7): `CreateClubAction::handle()` requires a
 * `ClubRegistrationFlowType` — an OPERAND enum, not a state enum — so {@see createViaAction()} CONSTRUCTS it from
 * the form value (`ClubRegistrationFlowType::from(...)`), the import the {Models, Actions, Enums} carve-out
 * admits for OperatorPanel (ADR 2026-06-21, task group 1). The per-Club `fee` is assembled as a {@see Money}
 * (integer minor units + ISO 4217, never a float — invariant 6/D11) ONLY when both an amount and a currency are
 * supplied, else `null` (the action's `?Money $fee = null` default).
 *
 * The page/action class-name collision (this page is `CreateClub`, the domain action is also `CreateClub`) is
 * resolved by aliasing the action import to `CreateClubAction` (design D6, mirrors `CreateProducer`). A
 * non-existent operating Producer is rejected by the action with a localized `MissingClubProducer` (a
 * `RuntimeException`), surfaced by the base's catch on {@see createRejectionField()} (`producer_id`) as a form
 * error rather than a 500.
 */
class CreateClub extends OperatorConsoleCreateRecord
{
    protected static string $resource = ClubResource::class;

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
        // Parties action's typed contract at the boundary. The required display_name/producer_id/
        // registration_flow_type make the happy path well-formed; the fee amount/currency and the generates_credit
        // flag are optional. InvalidArgumentException is a LogicException, so it propagates past the base's
        // RuntimeException catch — a programming bug, not a form error.
        $displayName = $data['display_name'] ?? null;
        $producerId = $data['producer_id'] ?? null;
        $registrationFlowType = $data['registration_flow_type'] ?? null;
        $amount = $data['amount'] ?? null;
        $currency = $data['currency'] ?? null;
        $generatesCredit = $data['generates_credit'] ?? true;

        if (
            ! is_string($displayName)
            || ! is_numeric($producerId)
            || ! is_string($registrationFlowType)
            || ! (is_null($amount) || $amount === '' || is_numeric($amount))
            || ! (is_null($currency) || is_string($currency))
            || ! is_bool($generatesCredit)
        ) {
            throw new InvalidArgumentException('Unexpected Club create payload.');
        }

        // Fee only when BOTH an amount and a currency are present, else null (D11). Money is integer minor units
        // + ISO 4217 — never a float (invariant 6).
        $fee = (is_numeric($amount) && is_string($currency) && $currency !== '')
            ? Money::of((int) $amount, Currency::of($currency))
            : null;

        return app(CreateClubAction::class)->handle(
            displayName: $displayName,
            producerId: (int) $producerId,
            registrationFlowType: ClubRegistrationFlowType::from($registrationFlowType),
            fee: $fee,
            generatesCredit: $generatesCredit,
        );
    }
}
