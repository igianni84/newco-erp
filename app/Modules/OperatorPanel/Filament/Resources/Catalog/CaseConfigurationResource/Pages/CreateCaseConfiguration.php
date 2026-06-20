<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\CaseConfigurationResource\Pages;

use App\Modules\Catalog\Actions\CreateCaseConfiguration as CreateCaseConfigurationAction;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleCreateRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CaseConfigurationResource;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The write-through Create page for a Case Configuration (operator-console-catalog-spine, task 2.2; design
 * L1/L3/L5; ADR 2026-06-19 + 2026-06-20; spec — Operator creates the standalone reference entities through the
 * console).
 *
 * The console NEVER saves the model directly: the kit base's `handleRecordCreation()` delegates to
 * {@see createViaAction()} here, which routes the form data into the Catalog domain action
 * {@see CreateCaseConfigurationAction} and returns the new `CaseConfiguration`. Filament's default
 * `new Model($data); $record->save()` stays fully overridden by the base — there is no `$model->save()` here
 * (the no-Eloquent-write PHPStan rule, task 1.2, guards it). The actor envelope (`actor_role: newco_ops` + the
 * operator id) is resolved by the action through the platform `ActorContext` seam off the authenticated
 * `operator` guard — the page constructs none. There is NO breakability input (BR-RefData-2): the action takes
 * none, and the form collects only `name`/`units_per_case`/`packaging_type`.
 *
 * A Case Configuration ships NO create-time guard (it is standalone, with no uniqueness rule — design L5), so
 * the inherited create-rejection→form-error catch never fires for it; {@see createRejectionField()} is a
 * harmless safety net for uniformity with the guarded entities (Product Master, Composite SKU).
 */
class CreateCaseConfiguration extends OperatorConsoleCreateRecord
{
    protected static string $resource = CaseConfigurationResource::class;

    protected function createRejectionField(): string
    {
        return 'name';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createViaAction(array $data): Model
    {
        // Filament types the post-validation form state as array<string, mixed>; narrow each value to the
        // Catalog action's typed contract at the boundary. The form's `required` fields make the happy path
        // well-formed — the guard fails loudly on the impossible mismatch rather than coercing silently.
        // InvalidArgumentException is a LogicException, so it propagates past the base's RuntimeException catch
        // — a programming bug, not a form error.
        $name = $data['name'];
        $unitsPerCase = $data['units_per_case'];
        $packagingType = $data['packaging_type'];

        if (! is_string($name) || ! is_numeric($unitsPerCase) || ! is_string($packagingType)) {
            throw new InvalidArgumentException('Unexpected Case Configuration create payload.');
        }

        return app(CreateCaseConfigurationAction::class)->handle(
            name: $name,
            unitsPerCase: (int) $unitsPerCase,
            packagingType: $packagingType,
        );
    }
}
