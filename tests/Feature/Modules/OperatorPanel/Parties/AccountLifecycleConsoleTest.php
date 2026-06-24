<?php

// Tasks 6.1–6.2 (operator-console-parties-membership; design D4) — the Account status-FSM WRITE surface on
// ViewCustomer. The three form-less Account verbs (suspendAccount, reactivateAccount, closeAccount) the page APPENDS
// to its SurfacesDomainActions-built header-action array, each routing through a Parties Account action by the
// co-provisioned 1:1 Account id and NEVER writing the model itself (the no-Eloquent-write rule).
//
// THE ACCOUNT VERBS ARE VISIBILITY-GATED to their legal Account `status` from-state (design D4), and the from-states
// OVERLAP: `active` surfaces suspendAccount + closeAccount, `suspended` surfaces reactivateAccount + closeAccount,
// `closed` surfaces none. Because each ->visible() predicate is the EXACT COMPLEMENT of the domain from-state guard,
// a rejected transition is UNREACHABLE through the surface — the verb is simply hidden; its reject is proven by a
// domain toThrow + assertActionHidden, never an action_failed the page can't raise (the Filament hidden-action
// landmine, lessons.md 2026-06-22). There is NO activateAccount: the Account is born `active` (AC-K-FSM-9; design L8).
//
// THE ACCOUNT VERBS ARE AUDIT-ONLY (design L8; § 15 names no Account-family event): a transition writes ONLY
// Account.status and records nothing — every assertion below pins DomainEvent::count() === 0. They are also ORTHOGONAL
// to the Customer status FSM (§ 4.7): an Account transition never moves the Customer or its Profiles (AC-K-FSM-9).
//
// DatabaseMigrations (mirroring CustomerKycSanctionsConsoleTest): each console action drives a real domain action
// opening its OWN DB::transaction, so the in-transaction write commits for real (RefreshDatabase would wrap every
// write in a never-committed outer transaction). The factories bypass the actions → record no event, and the Customer
// factory co-provisions no Account, so each test stands its Account up explicitly. Parties enums/models/actions are
// imported freely here: the {Models, Actions} import-boundary carve-out governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\ViewCustomer;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Actions\CloseAccount;
use App\Modules\Parties\Actions\ReactivateAccount;
use App\Modules\Parties\Actions\SuspendAccount;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Exceptions\IllegalAccountTransition;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

// ── 6.1 · The visibility sweep (design D4 — overlapping from-states) ──────────────────────────────────────────────
// The three verbs share from-states (closeAccount overlaps both suspend's `active` and reactivate's `suspended`), so
// the single-verb map doesn't fit — the sweep maps each Account status to the SET of verbs visible from it.

it('shows each Account verb only from its legal Account status — overlapping from-states (design D4)', function (AccountStatus $from, array $visibleVerbs) {
    actingAs(Operator::factory()->create(), 'operator');

    // The page reads `$customer->account?->status` at render — stand the co-provisioned Account up in the from-state.
    $customer = Customer::factory()->create();
    Account::factory()->create(['customer_id' => $customer->id, 'status' => $from]);

    $component = Livewire::test(ViewCustomer::class, ['record' => $customer->id]);

    foreach (['suspendAccount', 'reactivateAccount', 'closeAccount'] as $verb) {
        if (in_array($verb, $visibleVerbs, true)) {
            $component->assertActionVisible($verb);
        } else {
            $component->assertActionHidden($verb);
        }
    }
})->with([
    'active → suspend + close' => [AccountStatus::Active, ['suspendAccount', 'closeAccount']],
    'suspended → reactivate + close' => [AccountStatus::Suspended, ['reactivateAccount', 'closeAccount']],
    'closed → none' => [AccountStatus::Closed, []],
]);

it('exposes no Account activation verb — there is no ActivateAccount Action (design L8 / AC-K-FSM-9)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The Account is co-provisioned born `active` (no `pending` birth), so its only `→ active` edge is the restore
    // reactivateAccount (`suspended → active`) — there is NO ActivateAccount (AC-K-FSM-9; design L8). The console
    // registers no such verb: it is ABSENT (assertActionDoesNotExist), not merely hidden — nothing to gate on.
    $customer = Customer::factory()->create();
    Account::factory()->create(['customer_id' => $customer->id, 'status' => AccountStatus::Suspended]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->assertActionDoesNotExist('activateAccount');
});

// ── 6.1/6.2 · The happy paths (audit-only — design L8; orthogonal — AC-K-FSM-9) ───────────────────────────────────

it('suspends an active Account through the console — suspended, ZERO domain events, the Customer status and its Profiles unchanged (orthogonality — AC-K-FSM-9)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // An `active` Customer with an `active` Account and an `active` Profile: suspending the ACCOUNT is orthogonal to
    // the Customer status FSM and the Profile FSM (§ 4.7 — the Account FSM runs parallel, it does NOT cascade). It must
    // move ONLY Account.status and record NO event (audit-only — § 15 names no Account event, design L8). The factories
    // record nothing, so "zero events" is a clean post-condition (no baseline arithmetic).
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    $account = Account::factory()->create(['customer_id' => $customer->id, 'status' => AccountStatus::Active]);
    $profile = Profile::factory()->create(['customer_id' => $customer->id, 'state' => ProfileState::Active]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        // callAction asserts-visible-first, then drives the form-less verb into SuspendAccount by the Account id — the
        // console writes nothing itself (the no-Eloquent-write rule).
        ->callAction('suspendAccount')
        ->assertNotified((string) __('operator_console.customer.notifications.account_suspended'));

    expect($account->refresh()->status)->toBe(AccountStatus::Suspended)
        // … the Customer status is UNTOUCHED (the Account FSM is orthogonal — AC-K-FSM-9) …
        ->and($customer->refresh()->status)->toBe(CustomerStatus::Active)
        // … the Profile state is UNTOUCHED (an Account transition triggers no Profile cascade) …
        ->and($profile->refresh()->state)->toBe(ProfileState::Active)
        // … and NOTHING was recorded — every Account transition is audit-only (design L8).
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('reactivates a suspended Account through the console — active, ZERO domain events (audit-only)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $customer = Customer::factory()->create();
    $account = Account::factory()->create(['customer_id' => $customer->id, 'status' => AccountStatus::Suspended]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('reactivateAccount')
        ->assertNotified((string) __('operator_console.customer.notifications.account_reactivated'));

    expect($account->refresh()->status)->toBe(AccountStatus::Active)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('closes an Account through the console from active or suspended — closed, ZERO domain events (terminal, audit-only)', function (AccountStatus $from) {
    actingAs(Operator::factory()->create(), 'operator');

    // Closure is reachable from `active` (a live Account) OR `suspended` (a held one) — § 4.7. `closed` is terminal.
    $customer = Customer::factory()->create();
    $account = Account::factory()->create(['customer_id' => $customer->id, 'status' => $from]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('closeAccount')
        ->assertNotified((string) __('operator_console.customer.notifications.account_closed'));

    expect($account->refresh()->status)->toBe(AccountStatus::Closed)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'from active' => [AccountStatus::Active],
    'from suspended' => [AccountStatus::Suspended],
]);

// ── 6.1 · The reject FLOOR (design D4 — the hidden-action landmine, overlapping from-states) ──────────────────────
// Each verb's ->visible() predicate is the EXACT COMPLEMENT of its domain from-state guard, so an out-of-state call is
// UNREACHABLE through the surface: the verb is hidden, and a hidden Filament action can't be mounted/invoked
// (callAction asserts-visible-FIRST). So the reject is proven the only way the surface allows — the surface HIDES it
// (assertActionHidden) AND the domain INDEPENDENTLY rejects an out-of-band call (a domain toThrow), with Account.status
// and the (empty) event log unchanged — NEVER an assertNotified(action_failed) the page can't raise. Each verb maps to
// the list of its legal from-states; for the case state, the verbs whose legal-froms include it are skipped (covered by
// the happy-path / visibility tests) and every other verb is asserted hidden + a domain throw.

it('proves the Account verb reject floor — each verb hidden out of its legal from-state AND the domain rejects an out-of-band call, Account status + the event log unchanged (design D4)', function (AccountStatus $from) {
    actingAs(Operator::factory()->create(), 'operator');

    $customer = Customer::factory()->create();
    $account = Account::factory()->create(['customer_id' => $customer->id, 'status' => $from]);
    $accountId = $account->id;

    $component = Livewire::test(ViewCustomer::class, ['record' => $customer->id]);

    /** @var array<string, list<AccountStatus>> each verb's legal from-state(s) */
    $legalFroms = [
        'suspendAccount' => [AccountStatus::Active],
        'reactivateAccount' => [AccountStatus::Suspended],
        'closeAccount' => [AccountStatus::Active, AccountStatus::Suspended],
    ];

    // Literal app(X::class) per verb (never app($var) — Larastan can't resolve handle() on a variable class-string,
    // the multi-from-state corollary in progress.md).
    $invokeOutOfBand = [
        'suspendAccount' => fn () => app(SuspendAccount::class)->handle($accountId),
        'reactivateAccount' => fn () => app(ReactivateAccount::class)->handle($accountId),
        'closeAccount' => fn () => app(CloseAccount::class)->handle($accountId),
    ];

    foreach ($legalFroms as $verb => $froms) {
        if (in_array($from, $froms, true)) {
            continue; // a legal from-state for this verb — covered by the happy-path / visibility tests.
        }

        // Half 1 — the surface HIDES the verb (callAction would assert-visible-FIRST and fail) …
        $component->assertActionHidden($verb);
        // Half 2 — the domain INDEPENDENTLY rejects an out-of-band call, before any write (the from-state guard
        // re-reads lockForUpdate and asserts the from-state, then rolls back).
        expect($invokeOutOfBand[$verb])->toThrow(IllegalAccountTransition::class);
    }

    // Nothing moved: the Account status is exactly as arranged and the event log is still empty (every rejected call
    // rolled back before any write — Account transitions are audit-only).
    expect($account->refresh()->status)->toBe($from)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'active' => [AccountStatus::Active],
    'suspended' => [AccountStatus::Suspended],
    'closed' => [AccountStatus::Closed],
]);
