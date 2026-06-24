<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleCreateRecord;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource;
use App\Modules\Parties\Actions\CreateProfile as CreateProfileAction;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The write-through Create page for a Profile — the membership-application create (operator-console-parties-membership,
 * task 2.1; design D6). The console NEVER saves the model: the kit base's `handleRecordCreation()` delegates to
 * {@see createViaAction()} here, which narrows the form's Customer + Club selects to the Parties domain action
 * `CreateProfile`'s typed int contract and returns the new `Profile` (born `applied`, recording exactly one
 * `ProfileCreated`; this slice writes no transition out of `applied` — design D2). Filament's default
 * `new Model($data); $record->save()` stays fully overridden by the base — there is no `$model->save()` here (the
 * no-Eloquent-write PHPStan rule guards it). The actor envelope (`actor_role: newco_ops` + the operator id) is
 * resolved by the action through the platform `ActorContext` seam off the authenticated `operator` guard — the page
 * constructs none.
 *
 * The page/action class-name collision (this page is `CreateProfile`, the domain action is also `CreateProfile`) is
 * resolved by aliasing the action import to `CreateProfileAction` (mirrors the sibling `CreateCustomer as
 * CreateCustomerAction`). The create operands are plain ints, so this page imports NO `Parties\Enums` and the
 * operand-enum carve-out (ADR 2026-06-21) is not exercised. A duplicate non-terminal (Customer, Club) pair is
 * rejected by the action's in-transaction BR-K-Identity-2 guard with a localized DuplicateProfileForClub (a
 * `RuntimeException`, named in prose so Pint cannot re-add a forbidden `Parties\Exceptions` import — lessons.md
 * 2026-06-20) which the base catch maps to a form error on {@see createRejectionField()} (`club_id`).
 */
class CreateProfile extends OperatorConsoleCreateRecord
{
    protected static string $resource = ProfileResource::class;

    /**
     * The form field a localized create-rejection surfaces on — the Club select. A duplicate non-terminal
     * (Customer, Club) pair lands a DuplicateProfileForClub here (the action's BR-K-Identity-2 guard).
     */
    protected function createRejectionField(): string
    {
        return 'club_id';
    }

    /**
     * Route the create through the Parties `CreateProfile` action: narrow the validated Customer + Club select
     * state to the action's typed int contract at the boundary and return the new `Profile` (born `applied`). The
     * `required` selects make the happy path well-formed; a non-numeric payload is a programming bug — the
     * `InvalidArgumentException` (a `LogicException`) propagates past the base's `RuntimeException` create-rejection
     * catch, never becoming a silent form error. A duplicate live pair surfaces as DuplicateProfileForClub on
     * `club_id` (mapped by the base catch).
     *
     * @param  array<string, mixed>  $data
     */
    protected function createViaAction(array $data): Model
    {
        $customerId = $data['customer_id'] ?? null;
        $clubId = $data['club_id'] ?? null;

        if (! is_numeric($customerId) || ! is_numeric($clubId)) {
            throw new InvalidArgumentException('Unexpected Profile create payload.');
        }

        return app(CreateProfileAction::class)->handle(
            customerId: (int) $customerId,
            clubId: (int) $clubId,
        );
    }
}
