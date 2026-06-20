<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages;

use App\Modules\Catalog\Actions\CreateProductMaster as CreateProductMasterAction;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleCreateRecord;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource;
use App\Platform\I18n\TranslatableText;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The write-through Create page for a Product Master (operator-console-catalog-master, task 3.1; retrofitted
 * onto the shared {@see OperatorConsoleCreateRecord} kit in operator-console-catalog-spine, task 1.2; design
 * L2/L5/L6/L8; ADR 2026-06-19 + 2026-06-20; spec — Operator creates a Product Master through the console).
 *
 * The console NEVER saves the model directly: the kit base's `handleRecordCreation()` delegates to
 * {@see createViaAction()} here, which routes the form data into the Catalog domain action
 * {@see CreateProductMasterAction} (the manual baseline path — the LWIN/Liv-ex enrichment adapter is not
 * shipped) and returns the new `ProductMaster`. Filament's default `new Model($data); $record->save()` stays
 * fully overridden by the base — there is no `$model->save()` here (the no-Eloquent-write PHPStan rule, task
 * 1.2, guards it). The actor envelope (`actor_role: newco_ops` + the operator id) is resolved by the action
 * through the platform `ActorContext` seam off the authenticated `operator` guard — the page constructs none.
 *
 * The BR-Identity-1 identity-key collision (for WINE: producer + name + appellation against a non-retired
 * Master) is a DOMAIN rule — never reimplemented here. The action throws its localized rejection (a
 * RuntimeException — named in prose, never imported, keeping the cross-module surface exactly {Models, Actions});
 * the kit base catches it by base type and re-raises it as a form validation error on {@see createRejectionField()}
 * (`name`), so a duplicate is surfaced to the operator instead of a 500.
 */
class CreateProductMaster extends OperatorConsoleCreateRecord
{
    protected static string $resource = ProductMasterResource::class;

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
        // Catalog action's typed contract at the boundary. The form's `required` string fields and the
        // producer-id select make the happy path well-formed — the guard fails loudly on the impossible
        // mismatch rather than coercing silently (winery_story is the one optional input). InvalidArgumentException
        // is a LogicException, so it propagates past the base's RuntimeException catch — a programming bug, not a
        // form error.
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

        return app(CreateProductMasterAction::class)->handle(
            name: $name,
            producerId: (int) $producerId,
            appellation: $appellation,
            region: $region,
            wineryStory: ($wineryStory === null || $wineryStory === '')
                ? null
                : TranslatableText::of(['en' => $wineryStory]),
        );
    }
}
