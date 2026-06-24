<?php

namespace App\Modules\OperatorPanel\Filament\Console;

use Filament\Support\Contracts\HasLabel;

/**
 * OperatorConsoleNavigationGroup — the operator panel's sidebar navigation groups, one per spec module surfaced
 * as a console. Every {@see OperatorConsoleResource} declares its group through
 * {@see OperatorConsoleResource::navigationGroupCase()}, so the twelve catalog/parties consoles group by module
 * instead of rendering as one flat alphabetical list.
 *
 * Implementing {@see HasLabel} is the Filament v5 idiom for an ORDERED, LOCALIZED group: the displayed label
 * comes from {@see getLabel()} — resolved through `operator_console.navigation_group.<case>`, so it follows the
 * operator's locale (EN/IT) with the DEC-127 per-key EN fallback (invariant 12) — while the group ORDER is the
 * case-declaration order here (Catalog before Parties). That ordering is locale-independent and needs NO
 * `navigationGroups()` registration in the panel provider: Filament sorts enum-keyed groups by their `cases()`
 * position. A future module console adds its case here, in display order, and the panel groups it for free.
 */
enum OperatorConsoleNavigationGroup: string implements HasLabel
{
    case Catalog = 'catalog';
    case Parties = 'parties';

    public function getLabel(): string
    {
        return (string) __('operator_console.navigation_group.'.$this->value);
    }
}
