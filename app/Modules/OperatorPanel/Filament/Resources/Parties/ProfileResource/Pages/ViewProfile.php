<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\Concerns\SurfacesDomainActions;
use App\Modules\OperatorPanel\Filament\Resources\Parties\ProfileResource;
use App\Modules\Parties\Actions\ApproveProfile;
use App\Modules\Parties\Actions\CancelProfile;
use App\Modules\Parties\Actions\DeactivateProfile;
use App\Modules\Parties\Actions\DeclineProfile;
use App\Modules\Parties\Actions\LapseProfile;
use App\Modules\Parties\Actions\ReactivateProfile;
use App\Modules\Parties\Actions\RenewProfile;
use App\Modules\Parties\Actions\SetProfileAutoRenew;
use App\Modules\Parties\Actions\SuspendProfile;
use App\Modules\Parties\Models\Profile;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * ViewProfile — the Profile console view page (operator-console-parties-membership, task 1.2; design D1/D3/D4).
 *
 * Built at the TRAIT level, NOT through the catalog-shaped {@see OperatorConsoleViewRecord} base: that base
 * hard-codes the five catalog governance verbs (submit · reject · activate[SoD] · retire · reopen), which neither
 * match nor extend to the Profile's 8-verb membership FSM. So this page extends Filament's {@see ViewRecord}
 * directly and `use`s {@see SurfacesDomainActions}, assembling its OWN verb set in {@see getHeaderActions()} —
 * the established non-catalog pattern (ADR 2026-06-20 / 2026-06-21; the {@see ViewCustomer} precedent). The
 * read-only infolist lives on {@see ProfileResource}.
 *
 * The lifecycle verbs are APPENDED across groups, not replaced. Group 3 (landed) adds the membership-approval pair —
 * approve / decline — each a form-less {@see SurfacesDomainActions::lifecycleAction()} visibility-gated to
 * `{applied, waiting_list}` (design D4; parties-hero-package design D11): the predicate is the exact complement of
 * `ApproveProfile` / `DeclineProfile`'s widened from-state guard, so an out-of-state reject is structurally
 * unreachable through the surface (a hidden verb cannot be driven — lesson 2026-06-22). The domain still floors it
 * (`IllegalProfileTransition`, named in PROSE so Pint cannot re-add a boundary-breaching `Parties\Exceptions` import
 * — lesson 2026-06-20). Group 4 (landed) appends the status verbs —
 * suspend (`active → suspended`, state-preserving — only `state` moves, the active Club Credit untouched, AC-K-FSM-2a)
 * and reactivate (`suspended → active`). The former `activate` verb is GONE (RM-03 / MVP-DEC-016): approval now drives
 * `applied → active` atomically (approve = charge = activation), so `approved` is a transient pass-through, never a
 * durable resting state a verb could gate on. Group 5 (landed) appends the lapse / renew / terminal verbs:
 * lapse (`active → lapsed`, recording `ProfileExpired`), renew (`lapsed → active` within the 30-day grace, recording
 * `ProfileRenewed`) — the ONE verb whose reject is UI-reachable (design D5: the predicate can only check `state ==
 * lapsed`, so a past-grace renew is visible, the domain rejects it on the grace sub-gate, and `surfaceLifecycleOutcome`
 * surfaces `action_failed`), cancel (`active|lapsed → cancelled`, AUDIT-ONLY — no event exists; terminal soft-delete,
 * AC-K-FSM-13) and deactivate (`active → inactive`, recording `ProfileInactive`). The empty-set scaffold the resource's
 * `getPages()` needed to boot (the eager page-reference coupling — design Risks) is now populated.
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
     * approve (`applied | waiting_list → active`, recording the conditional first-ever `OriginatingClubLocked`; or, at
     * Hero-Package capacity, `applied → waiting_list`) and decline (`applied | waiting_list → rejected`, audit-only).
     * Both are form-less {@see SurfacesDomainActions::lifecycleAction()}s routing to their Parties action by the
     * Profile `int $id` (never an Eloquent write), each `->visible()`-gated to `applied` OR `waiting_list` via
     * {@see stateIs()} — the exact complement of the Actions' widened from-state guard, so an out-of-state call is
     * unreachable through the surface (lesson 2026-06-22) while the waitlist CONVERSION and the waitlist DECLINE are
     * both reachable (parties-hero-package design D11: before it, `approve` gated on `applied` alone and no operator
     * could ever convert a waitlisted Profile). Approve is the console's one verb with TWO lawful successful outcomes,
     * so its success key is a RESOLVER, not a fixed string — see {@see approvalOutcomeKey()}. A rejection still floors
     * at the domain (`IllegalProfileTransition`, a `RuntimeException` caught by base type in the trait — named in
     * PROSE, never a `{@see}`/`@throws` type, so Pint cannot add a forbidden `Parties\Exceptions` import, lesson
     * 2026-06-20); at capacity an already-waitlisted Profile has no edge left and its refusal reaches the operator as
     * the danger toast, carrying the domain's own localized capacity reason.
     * Group 4 appends the two status verbs, each gated to its own from-state via {@see stateIs()}: suspend
     * (`active → suspended`, state-preserving — only `state` moves, the active Club Credit untouched, AC-K-FSM-2a;
     * records `ProfileSuspended`) and reactivate (`suspended → active`; records `ProfileReactivated`). The former
     * `activate` verb is removed — approval drives `applied → active` atomically (RM-03 / MVP-DEC-016), so `approved`
     * never rests for a verb to gate on. Group 5 appends the lapse / renew / terminal verbs, each gated to its from-state via
     * {@see stateIs()}: lapse (`active → lapsed`; records `ProfileExpired`), renew (`lapsed → active`; records
     * `ProfileRenewed`) — the SOLE verb whose reject is UI-reachable: the predicate can only see `state == lapsed`, so a
     * past-grace renew is visible and the domain rejects it on the grace sub-gate, surfacing `action_failed` (design D5),
     * cancel (`active|lapsed → cancelled`; AUDIT-ONLY — no event, the `state` write IS the record) and deactivate
     * (`active → inactive`; records `ProfileInactive`). Finally, appended after the lifecycle verbs, the
     * {@see autoRenewAction()} auto-renew PREFERENCE affordance (Profile-5) — the one header action that is NOT a
     * lifecycle verb and carries no from-state gate.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->lifecycleAction('approve', $this->approvalOutcomeKey(...), fn (Model $record, string $notes) => app(ApproveProfile::class)->handle($this->recordOf(Profile::class, $record)->id))
                ->visible(fn (): bool => $this->stateIs('applied') || $this->stateIs('waiting_list')),
            $this->lifecycleAction('decline', 'declined', fn (Model $record, string $notes) => app(DeclineProfile::class)->handle($this->recordOf(Profile::class, $record)->id))
                ->visible(fn (): bool => $this->stateIs('applied') || $this->stateIs('waiting_list')),
            $this->lifecycleAction('suspend', 'suspended', fn (Model $record, string $notes) => app(SuspendProfile::class)->handle($this->recordOf(Profile::class, $record)->id))
                ->visible(fn (): bool => $this->stateIs('active')),
            $this->lifecycleAction('reactivate', 'reactivated', fn (Model $record, string $notes) => app(ReactivateProfile::class)->handle($this->recordOf(Profile::class, $record)->id))
                ->visible(fn (): bool => $this->stateIs('suspended')),
            $this->lifecycleAction('lapse', 'lapsed', fn (Model $record, string $notes) => app(LapseProfile::class)->handle($this->recordOf(Profile::class, $record)->id))
                ->visible(fn (): bool => $this->stateIs('active')),
            $this->lifecycleAction('renew', 'renewed', fn (Model $record, string $notes) => app(RenewProfile::class)->handle($this->recordOf(Profile::class, $record)->id))
                ->visible(fn (): bool => $this->stateIs('lapsed')),
            $this->lifecycleAction('cancel', 'cancelled', fn (Model $record, string $notes) => app(CancelProfile::class)->handle($this->recordOf(Profile::class, $record)->id))
                ->visible(fn (): bool => $this->stateIs('active') || $this->stateIs('lapsed')),
            $this->lifecycleAction('deactivate', 'deactivated', fn (Model $record, string $notes) => app(DeactivateProfile::class)->handle($this->recordOf(Profile::class, $record)->id))
                ->visible(fn (): bool => $this->stateIs('active')),
            $this->autoRenewAction(),
        ];
    }

    /**
     * The auto-renew PREFERENCE affordance (Profile-5, canon MVP-DEC-022; party-registry — *Profile Auto-Renewal
     * Preference*; parties-module-k-br-guards task 6.2). Unlike the lifecycle verbs above, `auto_renew` is a
     * last-writer-wins preference, NOT an FSM edge — an operator MAY set it in ANY state (§ Profile-5 imposes no
     * from-state restriction), so this affordance carries NO {@see stateIs()} visibility gate. It opens a
     * {@see Toggle} defaulted to the record's current `auto_renew` (read through the model, no `Parties\Enums` import)
     * and, on submit, drives the audit-only {@see SetProfileAutoRenew} action by the Profile `int $id` through
     * {@see SurfacesDomainActions::surfaceLifecycleOutcome()} — never an Eloquent write (the no-Eloquent-write rule).
     * That action records NO domain event (§ 15.2 names none for `auto_renew` — the audit-only contract, design D8);
     * the console surfaces only the success outcome. All copy localized (invariant 12).
     */
    private function autoRenewAction(): Action
    {
        return Action::make('setAutoRenew')
            ->label((string) __('operator_console.profile.actions.set_auto_renew'))
            ->form([
                Toggle::make('auto_renew')
                    ->label((string) __('operator_console.profile.fields.auto_renew'))
                    ->default(fn (): bool => $this->recordOf(Profile::class, $this->getRecord())->auto_renew),
            ])
            ->action(
                /** @param  array<string, mixed>  $data */
                function (Model $record, array $data): void {
                    $autoRenew = (bool) ($data['auto_renew'] ?? false);

                    $this->surfaceLifecycleOutcome(
                        fn () => app(SetProfileAutoRenew::class)->handle(
                            profileId: $this->recordOf(Profile::class, $record)->id,
                            autoRenew: $autoRenew,
                        ),
                        (string) __('operator_console.profile.notifications.auto_renew_set'),
                    );
                }
            );
    }

    /**
     * Which `notifications.*` copy truthfully names the outcome an `approve` click reached (parties-hero-package
     * design D11)? `approve` is the console's ONE verb with two lawful successful outcomes: with a free Hero-Package
     * seat the Profile reaches `active`, and at capacity the domain diverts an `applied` Profile to `waiting_list` —
     * a success, and NOT an approval. One fixed title would report the divert as an approval, which is the lie this
     * resolver exists to stop.
     *
     * This READS; it does not gate (design L4). `ApproveProfile` has already decided under the `parties_clubs` row
     * lock, written the state and committed by the time the resolver sees what it returned, so the console duplicates
     * no arithmetic and re-checks no capacity. It matches the resting state through the enum CAST's `->value`, never
     * by importing `App\Modules\Parties\Enums\ProfileState` (the {Models, Actions} read surface — design D2), exactly
     * as {@see stateIs()} does. The `mixed` parameter is the kit's contract (`Closure(mixed): string`): PHPStan max
     * enforces callable contravariance, so a `Profile`-typed resolver would not satisfy it — hence the `instanceof`
     * narrowing, which also makes any future non-model return fall back to the approved copy rather than fatal.
     */
    private function approvalOutcomeKey(mixed $outcome): string
    {
        return $outcome instanceof Profile && $outcome->state->value === 'waiting_list'
            ? 'waitlisted'
            : 'approved';
    }

    /**
     * Is the Profile's membership `state` exactly `$state` — the visibility predicate every lifecycle verb gates on
     * (design D4)? Read off the page record through the `ProfileState` CAST `->value` (e.g. `applied`, `approved`),
     * NEVER by importing `App\Modules\Parties\Enums\ProfileState` (the {Models, Actions} read surface — design D2);
     * `Profile::$state` is a non-nullable backed enum, so the comparison is a plain string match. ONE parametric
     * predicate serves every verb (approve/decline gate `applied` OR `waiting_list`, disjoined at the call site as
     * cancel already disjoins `active`/`lapsed`; the other group-4/5 verbs gate their own single from-state),
     * mirroring `ViewCustomer`'s `kycPending()` factoring.
     */
    private function stateIs(string $state): bool
    {
        return $this->recordOf(Profile::class, $this->getRecord())->state->value === $state;
    }
}
