<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages;

use App\Modules\Catalog\Actions\CreateProductVariant as CreateProductVariantAction;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleCreateRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource;
use App\Platform\I18n\TranslatableText;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The write-through Create page for a Product Variant (operator-console-catalog-spine, task 3.1; design
 * L1/L3/L5/L8; ADR 2026-06-19 + 2026-06-20; spec — Operator creates the hierarchical spine entities through the
 * console).
 *
 * The console NEVER saves the model directly: the kit base's `handleRecordCreation()` delegates to
 * {@see createViaAction()} here, which routes the form data into the Catalog domain action
 * {@see CreateProductVariantAction} and returns the new `ProductVariant`. Filament's default
 * `new Model($data); $record->save()` stays fully overridden by the base — there is no `$model->save()` here
 * (the no-Eloquent-write PHPStan rule, task 1.2, guards it). The action inserts the neutral core + the 1:1 WINE
 * attribute set and records `ProductVariantCreated` in one transaction; the actor envelope
 * (`actor_role: newco_ops` + the operator id) is resolved by the action through the platform `ActorContext`
 * seam off the authenticated `operator` guard — the page constructs none.
 *
 * A Product Variant ships NO create-time guard (its single-parent invariant BR-Identity-2 is enforced
 * structurally by the FK, not an app-layer check — design L5), so the inherited create-rejection→form-error
 * catch never fires for it; {@see createRejectionField()} is a harmless safety net for uniformity with the
 * guarded entities (Product Master, Composite SKU).
 */
class CreateProductVariant extends OperatorConsoleCreateRecord
{
    protected static string $resource = ProductVariantResource::class;

    protected function createRejectionField(): string
    {
        return 'variant_identifier';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createViaAction(array $data): Model
    {
        // Filament types the post-validation form state as array<string, mixed>; narrow each value to the
        // Catalog action's typed contract at the boundary. The form's `required` Master select + identifier make
        // the happy path well-formed; vintage_year and tasting_notes are optional (a non-vintage wine has no
        // year). InvalidArgumentException is a LogicException, so it propagates past the base's RuntimeException
        // catch — a programming bug, not a form error.
        $productMasterId = $data['product_master_id'];
        $variantIdentifier = $data['variant_identifier'];
        $vintageYear = $data['vintage_year'] ?? null;
        $tastingNotes = $data['tasting_notes'] ?? null;

        if (
            ! is_numeric($productMasterId)
            || ! is_string($variantIdentifier)
            || ! (is_null($vintageYear) || $vintageYear === '' || is_numeric($vintageYear))
            || ! (is_null($tastingNotes) || is_string($tastingNotes))
        ) {
            throw new InvalidArgumentException('Unexpected Product Variant create payload.');
        }

        return app(CreateProductVariantAction::class)->handle(
            productMasterId: (int) $productMasterId,
            variantIdentifier: $variantIdentifier,
            vintageYear: ($vintageYear === null || $vintageYear === '') ? null : (int) $vintageYear,
            nonVintage: (bool) ($data['non_vintage'] ?? false),
            tastingNotes: ($tastingNotes === null || $tastingNotes === '')
                ? null
                : TranslatableText::of(['en' => $tastingNotes]),
        );
    }
}
