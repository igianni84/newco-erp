<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleCreateRecord;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource;
use App\Modules\Parties\Actions\CreateProducer as CreateProducerAction;
use App\Platform\I18n\TranslatableText;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The write-through Create page for a Producer (operator-console-parties-producer, task 2.1; design D6; ADR
 * 2026-06-19 + 2026-06-20; spec — Operator creates a Producer through the console).
 *
 * The console NEVER saves the model directly: the kit base's `handleRecordCreation()` delegates to
 * {@see createViaAction()} here, which routes the form data into the Parties domain action
 * {@see CreateProducerAction} and returns the new `Producer` (born `draft`, recording `ProducerCreated`).
 * Filament's default `new Model($data); $record->save()` stays fully overridden by the base — there is no
 * `$model->save()` here (the no-Eloquent-write PHPStan rule guards it). The actor envelope
 * (`actor_role: newco_ops` + the operator id) is resolved by the action through the platform `ActorContext`
 * seam off the authenticated `operator` guard — the page constructs none.
 *
 * The page/action class-name collision (this page is `CreateProducer`, the domain action is also
 * `CreateProducer`) is resolved by aliasing the action import to `CreateProducerAction` (design D6, mirrors the
 * catalog `CreateSellableSku as CreateSellableSkuAction`). Producer ships NO create-time uniqueness guard (two
 * Producers with the same name both succeed — design D6), so the inherited create-rejection→form-error catch
 * never fires for it; {@see createRejectionField()} is a harmless safety net for uniformity with the guarded
 * entities.
 */
class CreateProducer extends OperatorConsoleCreateRecord
{
    protected static string $resource = ProducerResource::class;

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
        // Parties action's typed contract at the boundary. The form's `required` name/region/country make the
        // happy path well-formed; appellation/description/website are optional. InvalidArgumentException is a
        // LogicException, so it propagates past the base's RuntimeException catch — a programming bug, not a
        // form error.
        $name = $data['name'];
        $region = $data['region'];
        $country = $data['country'];
        $appellation = $data['appellation'] ?? null;
        $description = $data['description'] ?? null;
        $website = $data['website'] ?? null;

        if (
            ! is_string($name)
            || ! is_string($region)
            || ! is_string($country)
            || ! (is_null($appellation) || is_string($appellation))
            || ! (is_null($description) || is_string($description))
            || ! (is_null($website) || is_string($website))
        ) {
            throw new InvalidArgumentException('Unexpected Producer create payload.');
        }

        return app(CreateProducerAction::class)->handle(
            name: $name,
            region: $region,
            country: $country,
            appellation: ($appellation === null || $appellation === '') ? null : $appellation,
            description: ($description === null || $description === '')
                ? null
                : TranslatableText::of(['en' => $description]),
            website: ($website === null || $website === '') ? null : $website,
        );
    }
}
