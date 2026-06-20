<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource\Pages;

use App\Modules\Catalog\Actions\CreateProductReference as CreateProductReferenceAction;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleCreateRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductReferenceResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The write-through Create page for a Product Reference (operator-console-catalog-spine, task 3.2; design
 * L1/L3/L5/L8; ADR 2026-06-19 + 2026-06-20; spec — Operator creates the hierarchical spine entities through the
 * console).
 *
 * The console NEVER saves the model directly: the kit base's `handleRecordCreation()` delegates to
 * {@see createViaAction()} here, which routes the form data into the Catalog domain action
 * {@see CreateProductReferenceAction} and returns the new `ProductReference`. Filament's default
 * `new Model($data); $record->save()` stays fully overridden by the base — there is no `$model->save()` here
 * (the no-Eloquent-write PHPStan rule, task 1.2, guards it). The action inserts the row + records
 * `ProductReferenceCreated` in one transaction; the actor envelope (`actor_role: newco_ops` + the operator id)
 * is resolved by the action through the platform `ActorContext` seam off the authenticated `operator` guard —
 * the page constructs none.
 *
 * Product Reference is THE one create special case (design L5). Its `(variant, format)` identity (BR-Identity-3)
 * is enforced STRUCTURALLY by a database unique index, not an app-layer check — {@see CreateProductReferenceAction}
 * has NO dedup, so a duplicate pair surfaces as a framework {@see UniqueConstraintViolationException} with NO
 * localized domain message. That exception extends `RuntimeException` (via `QueryException` → `PDOException`),
 * so letting it propagate would hit the base's `RuntimeException` catch and render the raw SQL string. This page
 * therefore catches it SPECIFICALLY inside {@see createViaAction()} and re-raises a {@see ValidationException}
 * carrying a CONSOLE-OWNED localized message (`operator_console.product_reference.duplicate_reference` — the one
 * console-owned i18n key this change adds, since no domain copy exists); because `ValidationException` is not a
 * `RuntimeException`, it sails through the base catch untouched and Filament renders it as a form error — the raw
 * SQL is never shown (design L5). `UniqueConstraintViolationException` is a FRAMEWORK class, so importing it does
 * not touch the {Models, Actions} carve-out.
 *
 * {@see createRejectionField()} is a harmless safety net: PR's create ships no localized domain `RuntimeException`
 * guard (its only rejection is the framework duplicate, handled above), so the base's create-rejection→form-error
 * catch never fires for it.
 */
class CreateProductReference extends OperatorConsoleCreateRecord
{
    protected static string $resource = ProductReferenceResource::class;

    protected function createRejectionField(): string
    {
        return 'product_variant_id';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createViaAction(array $data): Model
    {
        // Filament types the post-validation form state as array<string, mixed>; narrow each value to the
        // Catalog action's typed contract at the boundary. The form's two `required` selects make the happy path
        // well-formed. InvalidArgumentException is a LogicException, so it propagates past the base's
        // RuntimeException catch — a programming bug, not a form error.
        $productVariantId = $data['product_variant_id'];
        $formatId = $data['format_id'];

        if (! is_numeric($productVariantId) || ! is_numeric($formatId)) {
            throw new InvalidArgumentException('Unexpected Product Reference create payload.');
        }

        try {
            return app(CreateProductReferenceAction::class)->handle(
                productVariantId: (int) $productVariantId,
                formatId: (int) $formatId,
            );
        } catch (UniqueConstraintViolationException) {
            // The DB unique index on (variant, format) rejected a duplicate pair (BR-Identity-3) — surface it as
            // a localized, console-owned form error on the Variant field. Never render the framework exception's
            // raw SQL message (design L5). ValidationException is not a RuntimeException, so the base's create
            // catch leaves it untouched.
            throw ValidationException::withMessages([
                'data.product_variant_id' => (string) __('operator_console.product_reference.duplicate_reference'),
            ]);
        }
    }
}
