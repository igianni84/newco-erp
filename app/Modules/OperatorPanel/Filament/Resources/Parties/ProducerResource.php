<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProducerResource\Pages;
use App\Modules\Parties\Models\Producer;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ProducerResource — the operator console's READ-ONLY surface over the Parties Producer
 * (operator-console-parties-producer, task 1.1; design D2/D7; ADR 2026-06-19 + 2026-06-20). The FIRST console
 * of the Parties (Module K) supply-side, built — like the catalog spine — on the shared kit, but as the
 * non-catalog trait-reuse pattern (the View page assembles its own verb set; see
 * `ProducerResource\Pages\ViewProducer`).
 *
 * It extends {@see OperatorConsoleResource} for the read-only conventions it shares with every console — the
 * `operator_console.<entity>` model labels off {@see i18nKey()} and the optimistic-lock `version` column
 * helper. It does NOT call the kit's `lifecycleStateColumn()`: Producer's state attribute is `status` (a
 * `ProducerStatus`), not the catalog `lifecycle_state`, and it additionally carries a separate provenance-KYC
 * lifecycle in `kyc_status` (a nullable `KycStatus`) — so this resource supplies its OWN `status` and
 * `kyc_status` badge columns (design D2). Producer is a STANDALONE winery registry (§ 4.4, not a Party
 * subtype) — no parent picker, no producer picker.
 *
 * It read-binds to {@see Producer} — the ADR-sanctioned exception, OperatorPanel-only and display-only: the
 * resource queries the model (and its WITHIN-Parties `clubs()` relation) for the list table + the view
 * infolist and NEVER writes it. Every mutation is a separate Filament Action routed through a Parties domain
 * action (the kit's create page + the bespoke view page); there is deliberately NO Edit page and NO
 * Delete/Create default action — the Parties backend ships no Producer update Action (post-creation field
 * edits are out of scope, proposal slice-boundary), and create lands on a write-through
 * {@see Pages\CreateProducer} page. The no-Eloquent-write PHPStan rule guards the discipline. Both enum
 * columns are rendered through their cast instances (`->value`), never by importing `App\Modules\Parties\Enums\*`,
 * so the console's cross-module surface stays exactly {Models, Actions} (the import-boundary carve-out). All
 * user-facing copy is localized through the `operator_console` group (invariant 12).
 */
class ProducerResource extends OperatorConsoleResource
{
    protected static ?string $model = Producer::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 4;

    protected static function i18nKey(): string
    {
        return 'producer';
    }

    protected static function navigationGroupCase(): OperatorConsoleNavigationGroup
    {
        return OperatorConsoleNavigationGroup::Parties;
    }

    /**
     * The create form (design D6/D8). Collects the scalar identity inputs the Parties `CreateProducer` action
     * consumes — the required name/region/country plus the optional appellation, website and (English-baseline,
     * translatable) description. It deliberately exposes NEITHER `status` NOR `kyc_status`: a Producer is born
     * `draft` with no KYC by the action, and both FSMs advance only through the view-page lifecycle actions
     * (tasks 3.1/4.1), never as create-form inputs. The form only COLLECTS; the write routes through the action
     * in {@see Pages\CreateProducer::createViaAction()} (there is no Edit page — the Parties backend ships no
     * Producer update Action). All labels localized (invariant 12).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label((string) __('operator_console.producer.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('region')
                    ->label((string) __('operator_console.producer.fields.region'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('country')
                    ->label((string) __('operator_console.producer.fields.country'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('appellation')
                    ->label((string) __('operator_console.producer.fields.appellation'))
                    ->maxLength(255),
                TextInput::make('website')
                    ->label((string) __('operator_console.producer.fields.website'))
                    ->maxLength(255),
                Textarea::make('description')
                    ->label((string) __('operator_console.producer.fields.description')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label((string) __('operator_console.producer.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('region')
                    ->label((string) __('operator_console.producer.columns.region')),
                TextColumn::make('country')
                    ->label((string) __('operator_console.producer.columns.country')),
                static::statusColumn(),
                static::kycStatusColumn(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label((string) __('operator_console.producer.columns.name')),
                TextEntry::make('region')
                    ->label((string) __('operator_console.producer.columns.region')),
                TextEntry::make('country')
                    ->label((string) __('operator_console.producer.columns.country')),
                TextEntry::make('appellation')
                    ->label((string) __('operator_console.producer.fields.appellation')),
                TextEntry::make('website')
                    ->label((string) __('operator_console.producer.fields.website')),
                TextEntry::make('description')
                    ->label((string) __('operator_console.producer.fields.description'))
                    ->getStateUsing(fn (Producer $record): ?string => $record->description?->resolve(app()->getLocale())),
                TextEntry::make('clubs')
                    ->label((string) __('operator_console.producer.fields.clubs'))
                    ->getStateUsing(fn (Producer $record): string => $record->clubs->pluck('display_name')->implode(', ')),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducers::route('/'),
            'create' => Pages\CreateProducer::route('/create'),
            'view' => Pages\ViewProducer::route('/{record}'),
        ];
    }

    /**
     * The Producer status badge column (design D2). Producer's state lives in `status` (a `ProducerStatus`),
     * not the catalog `lifecycle_state`, so the kit's `lifecycleStateColumn()` does not fit — this reads the
     * real attribute and renders it through its BackedEnum cast `->value` (e.g. `draft`), avoiding any
     * `Parties\Enums` import (the {Models, Actions} surface). `getAttribute()` is a read; the no-Eloquent-write
     * rule polices writes only.
     */
    protected static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label((string) __('operator_console.producer.columns.status'))
            ->badge()
            ->color(fn (string $state): string => static::stateBadgeColor($state))
            ->icon(fn (string $state): ?string => static::stateBadgeIcon($state))
            ->getStateUsing(function (Model $record): string {
                $state = $record->getAttribute('status');

                return $state instanceof BackedEnum ? (string) $state->value : '';
            });
    }

    /**
     * The Producer provenance-KYC badge column (design D2). `kyc_status` is a nullable `KycStatus` — a NULL
     * column is a Producer never touched by KYC (treated as cleared at the activation gate) — so the column
     * renders the empty string when the attribute is NULL, and the enum's `->value` (e.g. `pending`) otherwise.
     * Read-only and `Parties\Enums`-import-free, exactly like {@see statusColumn()}.
     */
    protected static function kycStatusColumn(): TextColumn
    {
        return TextColumn::make('kyc_status')
            ->label((string) __('operator_console.producer.columns.kyc_status'))
            ->badge()
            ->color(fn (string $state): string => static::stateBadgeColor($state))
            ->icon(fn (string $state): ?string => static::stateBadgeIcon($state))
            ->getStateUsing(function (Model $record): string {
                $state = $record->getAttribute('kyc_status');

                return $state instanceof BackedEnum ? (string) $state->value : '';
            });
    }
}
