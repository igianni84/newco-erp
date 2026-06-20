<?php

namespace App\Modules\OperatorPanel\Filament\Console;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * OperatorConsoleResource — the shared read-only base resource for every catalog operator console
 * (operator-console-catalog-spine, task 1.2; ADR 2026-06-20; design L1; it resolves the predecessor's design
 * L9 deferral). It owns the read-only conventions every spine console repeats so each `<Entity>Resource`
 * reduces to its own columns/fields/pages plus a one-line {@see i18nKey()}:
 *   - {@see getModelLabel()} / {@see getPluralModelLabel()} resolve `operator_console.<entity>.{label,
 *     plural_label}` off the per-entity {@see i18nKey()} (invariant 12);
 *   - {@see lifecycleStateColumn()} + {@see versionColumn()} build the two columns every catalog list shares —
 *     the `lifecycle_state` badge (rendered through its BackedEnum cast `->value`, e.g. `reviewed`, never an
 *     imported `Catalog\Enums` symbol — the {Models, Actions} carve-out, ADR 2026-06-19) and the
 *     optimistic-lock `version`.
 *
 * The base adds NO mutating action: the operator console READS module models for display and WRITES only
 * through module domain actions (ADR 2026-06-19) — there is no Edit/Delete default action here, and create
 * lands on a write-through {@see OperatorConsoleCreateRecord} page reached by a list-header navigation link. A
 * per-entity producer picker / cascade-retire is a Master-only extension and never lives here (design L6).
 *
 * It lives under `Filament/Console/` (not the discovered `Filament/Resources`), so this abstract base is never
 * auto-discovered by the panel's `discoverResources()` and never instantiated as a real resource; only the
 * concrete `<Entity>Resource` subclasses under `Filament/Resources/` are.
 */
abstract class OperatorConsoleResource extends Resource
{
    /**
     * The `operator_console.<key>` root for this entity's copy (e.g. `product_master`, `format`). It drives the
     * model labels and the shared column labels this base resolves.
     */
    abstract protected static function i18nKey(): string;

    public static function getModelLabel(): string
    {
        return (string) __('operator_console.'.static::i18nKey().'.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('operator_console.'.static::i18nKey().'.plural_label');
    }

    /**
     * The `lifecycle_state` badge column every catalog list carries. The state is read off the record and
     * rendered through its BackedEnum cast `->value` (e.g. `reviewed`) — matching each entity's
     * `lifecycle_state` cast and avoiding any `Catalog\Enums` import (the {Models, Actions} surface, ADR
     * 2026-06-19). `getAttribute()` is a read; the no-Eloquent-write rule (task 1.2) polices writes only.
     */
    protected static function lifecycleStateColumn(): TextColumn
    {
        return TextColumn::make('lifecycle_state')
            ->label((string) __('operator_console.'.static::i18nKey().'.columns.lifecycle_state'))
            ->badge()
            ->getStateUsing(function (Model $record): string {
                $state = $record->getAttribute('lifecycle_state');

                return $state instanceof BackedEnum ? (string) $state->value : '';
            });
    }

    /**
     * The optimistic-lock `version` column every catalog list carries.
     */
    protected static function versionColumn(): TextColumn
    {
        return TextColumn::make('version')
            ->label((string) __('operator_console.'.static::i18nKey().'.columns.version'));
    }
}
