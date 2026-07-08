<?php

namespace App\Modules\Catalog\Lifecycle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

/**
 * The catalog audit envelope's two derived fields, in ONE place: the `catalog.<segment>.<verb>` action string
 * and the stringified `entity_id` (catalog-module-0-completeness-sweep, design D3/D5).
 *
 * Module 0 now has TWO audit writers — {@see LifecycleTransition} (the lifecycle FSM steps and the two
 * `reviewed → reviewed` governance decisions) and {@see CatalogContentEdit} (the content edits) — and a THIRD
 * reader, {@see ApprovalGovernance}, derives the review-freshness condition by matching the `.<verb>` suffix of
 * those very action strings (design D4). If the two writers ever disagreed on how the `<segment>` is spelled,
 * the reader would silently stop seeing one writer's rows. The derivation therefore lives here, statically,
 * shared by both writers rather than copied into each.
 *
 * `<segment>` is derived from the model's own table (`catalog_product_masters` → `product_master`), the
 * canonical snake-case identifier: it can never drift from the entity it names, and it reads cleanly even for
 * the SKU acronyms (`catalog_sellable_skus` → `sellable_sku`, `catalog_composite_skus` → `composite_sku`).
 * `<verb>` is the writer's own vocabulary, governed by the D5 collision discipline (no new verb may END with one
 * of the four review-freshness suffixes unless it is meant to participate in review freshness).
 *
 * A pure static helper (no state, no injection): it maps a Model to two strings and is the sibling of neither
 * writer's mechanics. It touches only Eloquent's `getTable()`/`getKey()`, so it carries no module coupling.
 */
final class CatalogAuditEnvelope
{
    /** The audit `action` for a catalog step on $model: `catalog.<segment>.<verb>`. */
    public static function action(Model $model, string $verb): string
    {
        return 'catalog.'.self::segment($model).'.'.$verb;
    }

    /** The entity segment of the audit action, derived from the model's table (`catalog_product_masters` → `product_master`). */
    public static function segment(Model $model): string
    {
        return Str::singular(Str::after($model->getTable(), 'catalog_'));
    }

    /** The model's primary key as the audit / event envelope's string `entity_id`. */
    public static function entityId(Model $model): string
    {
        $key = $model->getKey();

        // Every catalog spine entity keys on an auto-increment integer; a non-scalar key is a structural bug.
        if (! is_int($key) && ! is_string($key)) {
            throw new LogicException('A catalog entity must have a scalar primary key.');
        }

        return (string) $key;
    }
}
