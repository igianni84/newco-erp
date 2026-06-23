<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\Concerns\SurfacesDomainActions;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource;
use App\Modules\Parties\Actions\ApproveProfile;
use App\Modules\Parties\Actions\DeclineProfile;
use App\Modules\Parties\Models\Profile;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

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
 * The lifecycle verbs are APPENDED across groups, not replaced. Group 3 (landed) adds the membership-approval pair —
 * approve / decline — each a form-less {@see SurfacesDomainActions::lifecycleAction()} visibility-gated to `applied`
 * (design D4): the predicate is the exact complement of `ApproveProfile` / `DeclineProfile`'s `applied` from-state
 * guard, so an out-of-state reject is structurally unreachable through the surface (a hidden verb cannot be driven —
 * lesson 2026-06-22). The domain still floors it (`IllegalProfileTransition`, named in PROSE so Pint cannot re-add a
 * boundary-breaching `Parties\Exceptions` import — lesson 2026-06-20). activate / suspend / reactivate (group 4) and
 * lapse / renew / cancel / deactivate (group 5) follow. The empty-set scaffold the resource's `getPages()` needed to
 * boot (the eager page-reference coupling — design Risks) is now populated.
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
     * The Profile's header actions — appended across groups (design D4). Group 3 adds the membership-approval pair:
     * approve (`applied → approved`, recording the conditional first-ever `OriginatingClubLocked`) and decline
     * (`applied → rejected`, audit-only). Both are form-less {@see SurfacesDomainActions::lifecycleAction()}s routing
     * to their Parties action by the Profile `int $id` (never an Eloquent write), each `->visible()`-gated to
     * `applied` via {@see stateIs()} — the exact complement of the Action's from-state guard, so an out-of-state call
     * is unreachable through the surface (lesson 2026-06-22). A rejection still floors at the domain
     * (`IllegalProfileTransition`, a `RuntimeException` caught by base type in the trait — named in PROSE, never a
     * `{@see}`/`@throws` type, so Pint cannot add a forbidden `Parties\Exceptions` import, lesson 2026-06-20).
     * activate / suspend / reactivate (group 4) and lapse / renew / cancel / deactivate (group 5) append here next.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->lifecycleAction('approve', 'approved', fn (Model $record, string $notes) => app(ApproveProfile::class)->handle($this->recordOf(Profile::class, $record)->id))
                ->visible(fn (): bool => $this->stateIs('applied')),
            $this->lifecycleAction('decline', 'declined', fn (Model $record, string $notes) => app(DeclineProfile::class)->handle($this->recordOf(Profile::class, $record)->id))
                ->visible(fn (): bool => $this->stateIs('applied')),
        ];
    }

    /**
     * Is the Profile's membership `state` exactly `$state` — the visibility predicate every lifecycle verb gates on
     * (design D4)? Read off the page record through the `ProfileState` CAST `->value` (e.g. `applied`, `approved`),
     * NEVER by importing `App\Modules\Parties\Enums\ProfileState` (the {Models, Actions} read surface — design D2);
     * `Profile::$state` is a non-nullable backed enum, so the comparison is a plain string match. ONE parametric
     * predicate serves every verb (approve/decline gate `applied`; the group-4/5 verbs gate their own from-states),
     * mirroring `ViewCustomer`'s `kycPending()` factoring.
     */
    private function stateIs(string $state): bool
    {
        return $this->recordOf(Profile::class, $this->getRecord())->state->value === $state;
    }
}
