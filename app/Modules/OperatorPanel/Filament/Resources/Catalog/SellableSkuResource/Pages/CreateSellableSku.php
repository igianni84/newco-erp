<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource\Pages;

use App\Modules\Catalog\Actions\CreateSellableSku as CreateSellableSkuAction;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleCreateRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The write-through Create page for a Sellable SKU (operator-console-catalog-spine, task 3.3; design
 * L1/L3/L5/L8; ADR 2026-06-19 + 2026-06-20; spec — Operator creates the hierarchical spine entities through the
 * console).
 *
 * The console NEVER saves the model directly: the kit base's `handleRecordCreation()` delegates to
 * {@see createViaAction()} here, which routes the form data into the Catalog domain action
 * {@see CreateSellableSkuAction} and returns the new `SellableSku`. Filament's default
 * `new Model($data); $record->save()` stays fully overridden by the base — there is no `$model->save()` here
 * (the no-Eloquent-write PHPStan rule, task 1.2, guards it). The action inserts the row + the commercial
 * attributes and records `SellableSKUCreated` in one transaction; the actor envelope (`actor_role: newco_ops` +
 * the operator id) is resolved by the action through the platform `ActorContext` seam off the authenticated
 * `operator` guard — the page constructs none.
 *
 * A Sellable SKU ships NO create-time guard (the spec defines no SKU uniqueness rule — a Product Reference +
 * Case Configuration pair may legitimately back many SKUs, BR-SKU-1 — and the single-PR / single-Case-Config
 * invariant is enforced structurally by the two FKs, not an app-layer check), so the inherited
 * create-rejection→form-error catch never fires for it; {@see createRejectionField()} is a harmless safety net
 * for uniformity with the guarded entities (Product Master, Composite SKU).
 */
class CreateSellableSku extends OperatorConsoleCreateRecord
{
    protected static string $resource = SellableSkuResource::class;

    protected function createRejectionField(): string
    {
        return 'commercial_name';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createViaAction(array $data): Model
    {
        // Filament types the post-validation form state as array<string, mixed>; narrow each value to the
        // Catalog action's typed contract at the boundary. The form's two `required` selects + required
        // commercial name make the happy path well-formed; marketing_copy is optional. InvalidArgumentException
        // is a LogicException, so it propagates past the base's RuntimeException catch — a programming bug, not a
        // form error.
        $productReferenceId = $data['product_reference_id'];
        $caseConfigurationId = $data['case_configuration_id'];
        $commercialName = $data['commercial_name'];
        $marketingCopy = $data['marketing_copy'] ?? null;

        if (
            ! is_numeric($productReferenceId)
            || ! is_numeric($caseConfigurationId)
            || ! is_string($commercialName)
            || ! (is_null($marketingCopy) || is_string($marketingCopy))
        ) {
            throw new InvalidArgumentException('Unexpected Sellable SKU create payload.');
        }

        return app(CreateSellableSkuAction::class)->handle(
            productReferenceId: (int) $productReferenceId,
            caseConfigurationId: (int) $caseConfigurationId,
            commercialName: $commercialName,
            marketingCopy: ($marketingCopy === null || $marketingCopy === '') ? null : $marketingCopy,
        );
    }
}
