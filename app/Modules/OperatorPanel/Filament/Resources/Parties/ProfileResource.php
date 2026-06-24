<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleNavigationGroup;
use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleResource;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use BackedEnum;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ProfileResource — the operator console's READ-ONLY surface over the Parties Profile, Module K's per-Club
 * membership entry (operator-console-parties-membership, task 1.1; design D1/D2/D3; ADR 2026-06-19 + 2026-06-20 +
 * 2026-06-21). The demand-side MEMBERSHIP console the Customer slice (`2026-06-22`) deferred: a STANDALONE,
 * cross-Customer Resource whose list doubles as the **membership approval queue** — the operationally central
 * view, defaulting to the pending `Applied` Profiles ({@see Pages\ListProfiles} tabs) — alongside the full 9-verb
 * Profile lifecycle that lands on {@see Pages\ViewProfile} (groups 3–5) and the write-through create surface on
 * {@see Pages\CreateProfile} (group 2).
 *
 * It extends {@see OperatorConsoleResource} for the read-only conventions every console shares — the
 * `operator_console.<entity>` model labels off {@see i18nKey()} and the optimistic-lock `version` column helper.
 * It does NOT call the kit's `lifecycleStateColumn()`: that renders the CATALOG `lifecycle_state` column; the
 * Profile carries its membership lifecycle on `state` (a `ProfileState`), so this resource supplies its OWN
 * {@see stateBadgeState()} badge, rendered through the BackedEnum cast `->value` (e.g. `applied`, `active`) and
 * NEVER by importing `App\Modules\Parties\Enums\*` (the {Models, Actions} read surface — design D2). The list
 * surfaces the within-Parties `customer()` / `club()` relations read-only (the boundary law forbids only
 * CROSS-module relations — Customer and Club are Module K).
 *
 * It read-binds to {@see Profile} — the ADR-sanctioned exception, OperatorPanel-only and display-only: the
 * resource queries the model (and its within-Parties relations) for the list table + the view infolist and NEVER
 * writes it. Every mutation is a separate Filament Action routed through a Parties domain action (the kit's create
 * page + the bespoke view page); there is deliberately NO Edit page and NO Delete/Create default action — create
 * lands on a write-through {@see Pages\CreateProfile} page, and the lifecycle verbs live on {@see Pages\ViewProfile}.
 * The no-Eloquent-write PHPStan rule guards the discipline. All user-facing copy is localized through the
 * `operator_console` group (invariant 12).
 */
class ProfileResource extends OperatorConsoleResource
{
    protected static ?string $model = Profile::class;

    protected static ?int $navigationSort = 2;

    protected static function i18nKey(): string
    {
        return 'profile';
    }

    protected static function navigationGroupCase(): OperatorConsoleNavigationGroup
    {
        return OperatorConsoleNavigationGroup::Parties;
    }

    /**
     * The create form — the membership-application inputs the Parties `CreateProfile` action consumes: a Customer
     * select (the within-Parties Customer registry, labelled email + name) and a Club select (labelled by the
     * Club's `display_name`). Both are WITHIN-Parties reads (the boundary law forbids only CROSS-module relations —
     * Customer and Club are Module K). It deliberately exposes NO `state` / `tier` / `role`: a Profile is born
     * `applied` by the action and single-tier/role at launch (DEC-062, design D6); the lifecycle verbs live on
     * {@see Pages\ViewProfile} (groups 3–5), never as a create-form input. The form only COLLECTS; the write routes
     * through the action in {@see Pages\CreateProfile::createViaAction()}, which narrows the selected ids to the
     * action's typed int contract. All labels localized (invariant 12).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->label((string) __('operator_console.profile.fields.customer'))
                    ->options(self::customerOptions(...))
                    ->required(),
                Select::make('club_id')
                    ->label((string) __('operator_console.profile.fields.club'))
                    ->options(self::clubOptions(...))
                    ->required(),
            ]);
    }

    /**
     * The read list — the cross-Customer membership registry whose {@see Pages\ListProfiles} tabs make it the
     * approval queue. The `customer` / `club` columns render the within-Parties relations through a closure (the
     * email primary, the name as a secondary line; the Club's `display_name`); the `state` badge renders the
     * `ProfileState` cast through {@see stateBadgeState()} (no `Parties\Enums` import — design D2); `version` is the
     * shared optimistic-lock column. NO mutating row/bulk action — the surface is read-only (the write verbs live
     * on the view/create pages).
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer')
                    ->label((string) __('operator_console.profile.columns.customer'))
                    ->getStateUsing(fn (Profile $record): string => $record->customer->email)
                    ->description(fn (Profile $record): string => $record->customer->name),
                TextColumn::make('club')
                    ->label((string) __('operator_console.profile.columns.club'))
                    ->getStateUsing(fn (Profile $record): string => $record->club->display_name),
                TextColumn::make('state')
                    ->label((string) __('operator_console.profile.columns.state'))
                    ->badge()
                    ->color(fn (string $state): string => static::stateBadgeColor($state))
                    ->icon(fn (string $state): ?string => static::stateBadgeIcon($state))
                    ->getStateUsing(self::stateBadgeState()),
            ]);
    }

    /**
     * The read-only view infolist: the within-Parties customer / club, the `state` badge (the cast `->value`,
     * never an imported enum — design D2), the single-tier `tier`, the demand-side lifecycle anchors `lapsed_at` /
     * `cancellation_reason`, and the optimistic-lock `version`. Every entry is display-only — the lifecycle writes
     * route through the {@see Pages\ViewProfile} header actions (groups 3–5), never an in-place field edit.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('customer')
                    ->label((string) __('operator_console.profile.columns.customer'))
                    ->getStateUsing(fn (Profile $record): string => $record->customer->email),
                TextEntry::make('club')
                    ->label((string) __('operator_console.profile.columns.club'))
                    ->getStateUsing(fn (Profile $record): string => $record->club->display_name),
                TextEntry::make('state')
                    ->label((string) __('operator_console.profile.columns.state'))
                    ->badge()
                    ->color(fn (string $state): string => static::stateBadgeColor($state))
                    ->icon(fn (string $state): ?string => static::stateBadgeIcon($state))
                    ->getStateUsing(self::stateBadgeState()),
                TextEntry::make('tier')
                    ->label((string) __('operator_console.profile.fields.tier')),
                TextEntry::make('lapsed_at')
                    ->label((string) __('operator_console.profile.fields.lapsed_at')),
                TextEntry::make('cancellation_reason')
                    ->label((string) __('operator_console.profile.fields.cancellation_reason')),
                TextEntry::make('version')
                    ->label((string) __('operator_console.profile.columns.version')),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProfiles::route('/'),
            'create' => Pages\CreateProfile::route('/create'),
            'view' => Pages\ViewProfile::route('/{record}'),
        ];
    }

    /**
     * The read resolver for the Profile's `state` badge — the membership-lifecycle enum read off the record and
     * rendered through its BackedEnum cast `->value` (e.g. `applied`, `approved`, `active`), or the empty string
     * if ever absent. It NEVER imports `App\Modules\Parties\Enums\ProfileState` (the {Models, Actions} read
     * surface — design D2); `getAttribute()` returns mixed, so the `instanceof BackedEnum` test is meaningful
     * (not always-true). It is a read — the no-Eloquent-write rule polices writes only.
     *
     * @return Closure(Model): string
     */
    private static function stateBadgeState(): Closure
    {
        return function (Model $record): string {
            $state = $record->getAttribute('state');

            return $state instanceof BackedEnum ? (string) $state->value : '';
        };
    }

    /**
     * Create-form Customer-select options, keyed by Customer id → the email + name label. A WITHIN-Parties read off
     * the {@see Customer} registry (no cross-module access — Customer is Module K); {@see Pages\CreateProfile}
     * narrows the selected id to the `CreateProfile` action's typed int contract. The id/email/name are domain data
     * (like a read column), not UI chrome, so no per-value i18n key is introduced — only the Select's own `label` is
     * localized.
     *
     * @return array<int, string>
     */
    private static function customerOptions(): array
    {
        $options = [];

        foreach (Customer::query()->orderBy('email')->get() as $customer) {
            $options[$customer->id] = $customer->email.' — '.$customer->name;
        }

        return $options;
    }

    /**
     * Create-form Club-select options, keyed by Club id → the `display_name` label. A WITHIN-Parties read off the
     * {@see Club} registry (no cross-module access — Club is Module K); {@see Pages\CreateProfile} narrows the
     * selected id to the action's typed int contract. Domain data, not UI chrome — only the Select's own `label` is
     * localized.
     *
     * @return array<int, string>
     */
    private static function clubOptions(): array
    {
        $options = [];

        foreach (Club::query()->orderBy('display_name')->get() as $club) {
            $options[$club->id] = $club->display_name;
        }

        return $options;
    }
}
