<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\FormatResource\Pages;

use App\Modules\Catalog\Actions\CreateFormat as CreateFormatAction;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleCreateRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\FormatResource;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The write-through Create page for a Format (operator-console-catalog-spine, task 2.1; design L1/L3/L5; ADR
 * 2026-06-19 + 2026-06-20; spec — Operator creates the standalone reference entities through the console).
 *
 * The console NEVER saves the model directly: the kit base's `handleRecordCreation()` delegates to
 * {@see createViaAction()} here, which routes the form data into the Catalog domain action
 * {@see CreateFormatAction} and returns the new `Format`. Filament's default `new Model($data); $record->save()`
 * stays fully overridden by the base — there is no `$model->save()` here (the no-Eloquent-write PHPStan rule,
 * task 1.2, guards it). The actor envelope (`actor_role: newco_ops` + the operator id) is resolved by the
 * action through the platform `ActorContext` seam off the authenticated `operator` guard — the page constructs
 * none.
 *
 * Format ships NO create-time guard (it is standalone, with no uniqueness rule — design L5), so the inherited
 * create-rejection→form-error catch never fires for it; {@see createRejectionField()} is a harmless safety net
 * for uniformity with the guarded entities (Product Master, Composite SKU).
 */
class CreateFormat extends OperatorConsoleCreateRecord
{
    protected static string $resource = FormatResource::class;

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
        $sizeLabel = $data['size_label'];
        $volumeMl = $data['volume_ml'];

        if (! is_string($name) || ! is_string($sizeLabel) || ! is_numeric($volumeMl)) {
            throw new InvalidArgumentException('Unexpected Format create payload.');
        }

        return app(CreateFormatAction::class)->handle(
            name: $name,
            sizeLabel: $sizeLabel,
            volumeMl: (int) $volumeMl,
        );
    }
}
