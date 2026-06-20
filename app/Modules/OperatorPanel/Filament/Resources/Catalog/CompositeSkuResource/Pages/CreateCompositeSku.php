<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource\Pages;

use App\Modules\Catalog\Actions\CreateCompositeSku as CreateCompositeSkuAction;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleCreateRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The write-through Create page for a Composite SKU (operator-console-catalog-spine, task 4.1; design
 * L1/L3/L5/L8; ADR 2026-06-19 + 2026-06-20; spec — Operator creates the hierarchical spine entities through the
 * console).
 *
 * The console NEVER saves the model directly: the kit base's `handleRecordCreation()` delegates to
 * {@see createViaAction()} here, which routes the form's ordered constituent list into the Catalog domain action
 * {@see CreateCompositeSkuAction} and returns the new `CompositeSku`. Filament's default
 * `new Model($data); $record->save()` stays fully overridden by the base — there is no `$model->save()` here
 * (the no-Eloquent-write PHPStan rule, task 1.2, guards it). The action normalises the list to its DISTINCT
 * constituents in input order, inserts the parent + the ordered constituent links and records
 * `CompositeSKUCreated` in one transaction; the actor envelope (`actor_role: newco_ops` + the operator id) is
 * resolved by the action through the platform `ActorContext` seam off the authenticated `operator` guard — the
 * page constructs none.
 *
 * A Composite SKU ships ONE create guard — the `< 2 distinct constituents` floor (BR-SKU-2), a localized domain
 * `InsufficientCompositeConstituents` (a `RuntimeException`). Unlike the Product Reference duplicate (a framework
 * `UniqueConstraintViolationException` with no domain message, caught
 * specially in that page) this rejection ALREADY carries a localized message, so it needs NO special catch here:
 * the inherited base catch maps `$e->getMessage()` to `data.<createRejectionField()>` (the constituents field) —
 * surfaced to the operator as a form error, design L5. A malformed payload throws an `InvalidArgumentException`
 * (a `LogicException`), which propagates past the base's `RuntimeException` catch — a programming bug, not a form
 * error. The action is PRODUCER-AGNOSTIC (design D9), so the page applies no producer filter or validation.
 */
class CreateCompositeSku extends OperatorConsoleCreateRecord
{
    protected static string $resource = CompositeSkuResource::class;

    protected function createRejectionField(): string
    {
        return 'constituents';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createViaAction(array $data): Model
    {
        // Filament types the post-validation form state as array<string, mixed>; narrow the multi-select state to
        // the Catalog action's `list<int>` contract at the boundary. The required multi-select makes the happy
        // path a non-empty array of option keys (in selection / bundle order); the `< 2 distinct` floor is the
        // action's job, surfaced as a form error by the base catch. InvalidArgumentException is a LogicException,
        // so a malformed payload propagates past the base's RuntimeException catch — a programming bug, not a form
        // error.
        $constituents = $data['constituents'] ?? [];

        if (! is_array($constituents)) {
            throw new InvalidArgumentException('Unexpected Composite SKU create payload.');
        }

        $productReferenceIds = [];
        foreach ($constituents as $constituent) {
            if (! is_numeric($constituent)) {
                throw new InvalidArgumentException('Unexpected Composite SKU constituent.');
            }

            $productReferenceIds[] = (int) $constituent;
        }

        return app(CreateCompositeSkuAction::class)->handle($productReferenceIds);
    }
}
