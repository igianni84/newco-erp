<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages;

use App\Modules\Catalog\Actions\CreateProductMaster as CreateProductMasterAction;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource;
use App\Platform\I18n\TranslatableText;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * The write-through Create page for a Product Master (operator-console-catalog-master, task 3.1; design
 * L2/L6/L8; ADR 2026-06-19; spec — Operator creates a Product Master through the console).
 *
 * The console NEVER saves the model directly: {@see handleRecordCreation()} routes the form data into the
 * Catalog domain action {@see CreateProductMasterAction} (the manual baseline path — the LWIN/Liv-ex
 * enrichment adapter is not shipped) and returns the new `ProductMaster`. Filament's default
 * `new Model($data); $record->save()` is fully overridden — there is no `$model->save()` here (the
 * no-Eloquent-write PHPStan rule, task 1.2, guards it). The actor envelope (`actor_role: newco_ops` +
 * the operator id) is resolved by the action through the platform `ActorContext` seam off the authenticated
 * `operator` guard — the page constructs no envelope itself.
 *
 * The BR-Identity-1 identity-key collision (for WINE: producer + name + appellation against a non-retired
 * Master) is a DOMAIN rule — never reimplemented here. The action throws its localized rejection (a
 * {@see RuntimeException}); the page catches it via the base type (NOT `use Catalog\Exceptions\…`, keeping
 * the console's cross-module surface exactly {Models, Actions} — the import-boundary carve-out, task 1.3)
 * and re-raises it as a form validation error on the `name` field, so a duplicate is surfaced to the
 * operator instead of a 500.
 */
class CreateProductMaster extends CreateRecord
{
    protected static string $resource = ProductMasterResource::class;

    /**
     * One clean create flow — no "create & create another" (every create routes through the domain action;
     * the simpler single path keeps the write-through surface minimal).
     */
    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        // Filament types the post-validation form state as array<string, mixed>; narrow each value to the
        // Catalog action's typed contract at the boundary. The form's `required` string fields and the
        // producer-id select make the happy path well-formed — the guard fails loudly on the impossible
        // mismatch rather than coercing silently (winery_story is the one optional input).
        $name = $data['name'];
        $producerId = $data['producer_id'];
        $appellation = $data['appellation'];
        $region = $data['region'];
        $wineryStory = $data['winery_story'] ?? null;

        if (
            ! is_string($name)
            || ! is_numeric($producerId)
            || ! is_string($appellation)
            || ! is_string($region)
            || ! (is_null($wineryStory) || is_string($wineryStory))
        ) {
            throw new InvalidArgumentException('Unexpected Product Master create payload.');
        }

        try {
            return app(CreateProductMasterAction::class)->handle(
                name: $name,
                producerId: (int) $producerId,
                appellation: $appellation,
                region: $region,
                wineryStory: ($wineryStory === null || $wineryStory === '')
                    ? null
                    : TranslatableText::of(['en' => $wineryStory]),
            );
        } catch (RuntimeException $exception) {
            // BR-Identity-1 dedup rejection (and any other domain runtime rejection on the create path) →
            // a form field error on `name`, not an unhandled 500. The message is already localized by the
            // action (lang/{en,it}/catalog.php); `data.name` is the form's state-path-qualified key that
            // Filament's assertHasFormErrors(['name']) resolves to.
            throw ValidationException::withMessages([
                'data.name' => $exception->getMessage(),
            ]);
        }
    }
}
