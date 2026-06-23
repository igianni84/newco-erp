<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\Concerns\SurfacesDomainActions;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * ViewProfile — the Profile console view page (operator-console-parties-membership, task 1.2; design D1/D3/D4).
 *
 * Built at the TRAIT level, NOT through the catalog-shaped {@see OperatorConsoleViewRecord} base: that base
 * hard-codes the five catalog governance verbs (submit · reject · activate[SoD] · retire · reopen), which neither
 * match nor extend to the Profile's 9-verb membership FSM. So this page extends Filament's {@see ViewRecord}
 * directly and `use`s {@see SurfacesDomainActions}, assembling its OWN verb set in {@see getHeaderActions()} —
 * the established non-catalog pattern (ADR 2026-06-20 / 2026-06-21; the {@see ViewCustomer} precedent). The
 * read-only infolist lives on {@see ProfileResource}.
 *
 * Scaffolded here with the trait wired and an EMPTY header-action set so the resource's `getPages()` boots (the
 * eager page-reference coupling — design Risks); the lifecycle verbs are APPENDED, not replaced: approve / decline
 * (group 3), activate / suspend / reactivate (group 4), lapse / renew / cancel / deactivate (group 5). Each will
 * be a form-less {@see SurfacesDomainActions::lifecycleAction()} visibility-gated to its from-state (design D4).
 */
class ViewProfile extends ViewRecord
{
    use SurfacesDomainActions;

    protected static string $resource = ProfileResource::class;

    protected function i18nKey(): string
    {
        return 'profile';
    }

    /**
     * The Profile's header actions. Empty in this group — the 9 lifecycle verbs are appended in groups 3–5 (each a
     * form-less, from-state-gated {@see SurfacesDomainActions::lifecycleAction()}, design D4).
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
