<?php

namespace App\Modules\OperatorPanel\Filament\Widgets;

use App\Modules\Parties\Models\Profile;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Str;

/**
 * MembershipsByStateChart — the dashboard's distribution chart (operator-console UI pass, 2026-06-24): the count
 * of Club memberships (Profiles) in each lifecycle state, the demand-side "membership funnel" that reads as real
 * analytics for the club business. It READS the Parties Profile model for display (an aggregate read; the
 * no-Eloquent-write rule polices writes only) and groups by the raw `state` token — rendered through Str::headline
 * for the axis labels, exactly as the consoles render enum `->value`s, so no per-state i18n key is invented. The
 * one piece of localized chrome (heading + dataset label) routes through the operator_console group (invariant 12).
 */
class MembershipsByStateChart extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        return (string) __('operator_console.dashboard.memberships_by_state.heading');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        /** @var array<string, int> $counts */
        $counts = Profile::query()
            ->selectRaw('state, count(*) as aggregate')
            ->groupBy('state')
            ->orderBy('state')
            ->pluck('aggregate', 'state')
            ->all();

        return [
            'datasets' => [
                [
                    'label' => (string) __('operator_console.dashboard.memberships_by_state.dataset'),
                    'data' => array_values($counts),
                    'backgroundColor' => '#A0715A',
                    'borderColor' => '#A0715A',
                ],
            ],
            'labels' => array_map(
                static fn (string $state): string => Str::headline($state),
                array_keys($counts),
            ),
        ];
    }
}
