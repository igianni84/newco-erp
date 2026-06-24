<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\SupplierResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleCreateRecord;
use App\Modules\OperatorPanel\Filament\Resources\Parties\SupplierResource;
use App\Modules\Parties\Actions\CreateSupplier as CreateSupplierAction;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The write-through Create page for a Supplier (operator-console UI pass, 2026-06-24).
 *
 * The console NEVER saves the model directly: the kit base's `handleRecordCreation()` delegates to
 * {@see createViaAction()} here, which routes the single legal-name input into the Parties domain action
 * {@see CreateSupplierAction} (which fixes `party_type = supplier` and records no event — § 15 names none) and
 * returns the new `Supplier`. The no-Eloquent-write PHPStan rule guards the discipline. The page/action
 * class-name collision is resolved by aliasing the action import to `CreateSupplierAction`.
 *
 * Supplier ships no create-time domain guard, so the inherited create-rejection→form-error catch never fires;
 * {@see createRejectionField()} is a harmless safety net for uniformity with the guarded entities.
 */
class CreateSupplier extends OperatorConsoleCreateRecord
{
    protected static string $resource = SupplierResource::class;

    protected function createRejectionField(): string
    {
        return 'legal_name';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createViaAction(array $data): Model
    {
        $legalName = $data['legal_name'] ?? null;

        if (! is_string($legalName)) {
            throw new InvalidArgumentException('Unexpected Supplier create payload.');
        }

        return app(CreateSupplierAction::class)->handle(legalName: $legalName);
    }
}
