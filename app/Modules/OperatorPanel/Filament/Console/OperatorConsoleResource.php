<?php

namespace App\Modules\OperatorPanel\Filament\Console;

use BackedEnum;
use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * OperatorConsoleResource — the shared read-only base resource for every catalog operator console
 * (operator-console-catalog-spine, task 1.2; ADR 2026-06-20; design L1; it resolves the predecessor's design
 * L9 deferral). It owns the read-only conventions every spine console repeats so each `<Entity>Resource`
 * reduces to its own columns/fields/pages plus a one-line {@see i18nKey()}:
 *   - {@see getModelLabel()} / {@see getPluralModelLabel()} resolve `operator_console.<entity>.{label,
 *     plural_label}` off the per-entity {@see i18nKey()} (invariant 12);
 *   - {@see lifecycleStateColumn()} builds the `lifecycle_state` badge every catalog list shares (rendered
 *     through its BackedEnum cast `->value`, e.g. `reviewed`, never an imported `Catalog\Enums` symbol — the
 *     {Models, Actions} carve-out, ADR 2026-06-19), colored + iconed at a glance via the semantic
 *     {@see stateBadgeColor()} / {@see stateBadgeIcon()} helpers (shared with the Parties status badges). The
 *     optimistic-lock `version` is intentionally NOT a list column — it is internal concurrency metadata, kept
 *     only in each entity's detail/infolist (a "Metadata" section), never in the at-a-glance table.
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

    /**
     * The navigation group this console belongs to — its spec module, surfaced as an ordered, localized sidebar
     * group (see {@see OperatorConsoleNavigationGroup}). Mirrors {@see i18nKey()}: each concrete console declares
     * its group in one line and the base wires it into Filament's {@see getNavigationGroup()}, so the sidebar
     * groups by module instead of one flat alphabetical list — and being abstract, it makes declaring a group
     * structurally mandatory for every future console (a new ungrouped Resource cannot compile).
     */
    abstract protected static function navigationGroupCase(): OperatorConsoleNavigationGroup;

    public static function getModelLabel(): string
    {
        return (string) __('operator_console.'.static::i18nKey().'.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('operator_console.'.static::i18nKey().'.plural_label');
    }

    /**
     * Wire the per-console {@see navigationGroupCase()} into Filament's navigation. Returning the enum (a
     * UnitEnum) — not its label string — lets Filament order the groups by `cases()` position and resolve the
     * localized label off {@see OperatorConsoleNavigationGroup::getLabel()} at render time.
     */
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return static::navigationGroupCase();
    }

    /**
     * The `lifecycle_state` badge column every catalog list carries. The state is read off the record and
     * rendered through its BackedEnum cast `->value` (e.g. `reviewed`) — matching each entity's
     * `lifecycle_state` cast and avoiding any `Catalog\Enums` import (the {Models, Actions} surface, ADR
     * 2026-06-19). `getAttribute()` is a read; the no-Eloquent-write rule (task 1.2) polices writes only.
     * The badge is colored + iconed semantically through {@see stateBadgeColor()} / {@see stateBadgeIcon()}.
     */
    protected static function lifecycleStateColumn(): TextColumn
    {
        return TextColumn::make('lifecycle_state')
            ->label((string) __('operator_console.'.static::i18nKey().'.columns.lifecycle_state'))
            ->badge()
            ->sortable()
            ->color(fn (string $state): string => static::stateBadgeColor($state))
            ->icon(fn (string $state): ?string => static::stateBadgeIcon($state))
            ->getStateUsing(function (Model $record): string {
                $state = $record->getAttribute('lifecycle_state');

                return $state instanceof BackedEnum ? (string) $state->value : '';
            });
    }

    /**
     * Branded list defaults every console table opts into so the surface is consistent and never reads as an
     * unstyled stock grid: newest-first ordering and a localized, iconed empty state (the shared
     * `operator_console.empty.*` copy) instead of Filament's bare "No records". Each resource's `table()`
     * starts from this and then adds its own columns + {@see stateFilter()}/`->filters([...])`. Pure table
     * configuration — no Eloquent write (task 1.2).
     */
    protected static function applyConsoleDefaults(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-circle-stack')
            ->emptyStateHeading((string) __('operator_console.empty.heading'))
            ->emptyStateDescription((string) __('operator_console.empty.description'));
    }

    /**
     * A SelectFilter over a lifecycle/status column ({@see stateBadgeColor}'s register). Options are derived
     * from the DISTINCT raw tokens actually present in the table (a query `pluck`, so casts are NOT applied —
     * the value is the raw string) and humanized via {@see Str::headline()}; this keeps the filter free of any
     * `Catalog\Enums`/`Parties\Enums` import (the {Models, Actions} surface, ADR 2026-06-19) and needs no
     * per-state i18n key, mirroring how {@see lifecycleStateColumn()} renders the bare token. `pluck` is a read
     * (no Eloquent write — task 1.2).
     */
    protected static function stateFilter(string $attribute = 'lifecycle_state', ?string $labelKey = null): SelectFilter
    {
        $labelKey ??= 'columns.'.$attribute;

        return SelectFilter::make($attribute)
            ->label((string) __('operator_console.'.static::i18nKey().'.'.$labelKey))
            ->options(fn (): array => static::getModel()::query()
                ->select($attribute)
                ->distinct()
                ->orderBy($attribute)
                ->pluck($attribute)
                ->mapWithKeys(function (mixed $value): array {
                    $token = match (true) {
                        $value instanceof BackedEnum => (string) $value->value,
                        is_string($value) => $value,
                        is_int($value) => (string) $value,
                        default => '',
                    };

                    return [$token => Str::headline($token)];
                })
                ->all());
    }

    /**
     * The shared infolist entry that renders a lifecycle/status attribute as the SAME semantic colored +
     * iconed badge the list tables carry ({@see stateBadgeColor()}/{@see stateBadgeIcon()}) — promoted out of
     * Product Master so every detail page shows state identically (the consistency fix: state was a badge in
     * lists but plain text in five infolists). Reads the attribute through its cast `->value`, never importing
     * a module enum.
     */
    protected static function badgedStateEntry(string $attribute = 'lifecycle_state', ?string $labelKey = null): TextEntry
    {
        $labelKey ??= 'columns.'.$attribute;

        return TextEntry::make($attribute)
            ->label((string) __('operator_console.'.static::i18nKey().'.'.$labelKey))
            ->badge()
            ->color(fn (string $state): string => static::stateBadgeColor($state))
            ->icon(fn (string $state): ?string => static::stateBadgeIcon($state))
            ->getStateUsing(function (Model $record) use ($attribute): string {
                $state = $record->getAttribute($attribute);

                return match (true) {
                    $state instanceof BackedEnum => (string) $state->value,
                    is_string($state) => $state,
                    default => '',
                };
            });
    }

    /**
     * The collapsed "Metadata" infolist section every detail page closes with — the optimistic-lock `version`
     * by default (internal concurrency metadata kept off the at-a-glance table, per {@see lifecycleStateColumn()}'s
     * note), or any caller-supplied entries (e.g. created/updated timestamps). Centralizing it keeps the
     * Metadata block identical across consoles.
     *
     * @param  array<int, Entry|Component>  $entries
     */
    protected static function metadataSection(array $entries = []): Section
    {
        if ($entries === []) {
            $entries = [
                TextEntry::make('version')
                    ->label((string) __('operator_console.'.static::i18nKey().'.columns.version')),
            ];
        }

        return Section::make((string) __('operator_console.'.static::i18nKey().'.sections.metadata'))
            ->icon('heroicon-o-clock')
            ->collapsed()
            ->schema($entries);
    }

    /**
     * Map a lifecycle/status token to a SEMANTIC Filament badge color, so every operator console reads state
     * AT A GLANCE: green = good/active, blue = in-review, amber = pending/transitional, red = terminal-negative,
     * gray = neutral/ended. Keyed on the raw string token — NOT the enum symbol — so this presentation concern
     * stays in the console and never imports `Catalog\Enums` / `Parties\Enums` (the {Models, Actions} surface,
     * ADR 2026-06-19). Unknown / empty tokens fall back to gray. Shared by {@see lifecycleStateColumn()} and
     * every per-entity status badge across the Catalog + Parties consoles.
     */
    protected static function stateBadgeColor(string $state): string
    {
        return match ($state) {
            'active', 'verified', 'approved', 'passed', 'cleared' => 'success',
            'reviewed', 'waiting_list' => 'info',
            'pending', 'applied', 'suspended', 'sunset', 'under_review' => 'warning',
            'rejected', 'closed', 'terminated', 'cancelled', 'lapsed', 'failed', 'flagged' => 'danger',
            default => 'gray', // draft, retired, superseded, inactive, not_required, '' …
        };
    }

    /**
     * The heroicon that pairs with {@see stateBadgeColor()}'s register — one glyph per semantic bucket, so a
     * badge carries an at-a-glance icon without a per-token explosion. Null for neutral/empty states (a bare
     * gray badge, no icon).
     */
    protected static function stateBadgeIcon(string $state): ?string
    {
        return match (static::stateBadgeColor($state)) {
            'success' => 'heroicon-m-check-circle',
            'info' => 'heroicon-m-information-circle',
            'warning' => 'heroicon-m-clock',
            'danger' => 'heroicon-m-x-circle',
            default => null,
        };
    }
}
